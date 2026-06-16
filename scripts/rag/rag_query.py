"""
RAG query helper.

Usage:
  - Ensure documents are in `scripts/rag/docs/`.
  - Optionally create embeddings/index using `ingest_docs.py`.
  - Set `OPENAI_API_KEY` to enable model-backed answers.
  - Run: `python rag_query.py --question "Cần nộp giấy tờ gì để được miễn học phí?"`

If no OpenAI key or FAISS index found, the script falls back to a simple keyword retriever
and prints the assembled prompt for manual inspection.
"""
import os
import argparse
from pathlib import Path
import json

try:
    import openai
    import numpy as np
    import faiss
except Exception:
    openai = None

DOCS_DIR = Path(__file__).parent / "docs"
TEMPLATE_PATH = Path(__file__).parent / "PROMPT_TEMPLATE.md"

def load_docs():
    docs = []
    for p in DOCS_DIR.glob("*.txt"):
        docs.append({"id": p.name, "text": p.read_text(encoding='utf-8'), "source": str(p)})
    for p in DOCS_DIR.glob("*.md"):
        docs.append({"id": p.name, "text": p.read_text(encoding='utf-8'), "source": str(p)})
    return docs

def simple_retriever(query, docs, topk=3):
    q = set(query.lower().split())
    scored = []
    for d in docs:
        words = set(d['text'].lower().split())
        score = len(q & words)
        scored.append((score, d))
    scored.sort(key=lambda x: x[0], reverse=True)
    return [d for s,d in scored[:topk] if s>0]

def assemble_context(docs):
    parts = []
    for d in docs:
        header = f"[Source: {d['id']} | {d['source']} ]\n"
        snippet = d['text'][:1500].replace('\n',' ')  # trim
        parts.append(header + snippet)
    return "\n\n".join(parts)

def build_prompt(question, retrieved_docs):
    if TEMPLATE_PATH.exists():
        tmpl = TEMPLATE_PATH.read_text(encoding='utf-8')
        return tmpl.replace('{user_question}', question).replace('{retrieved_docs}', retrieved_docs)
    else:
        return f"User: {question}\nCONTEXT:\n{retrieved_docs}"

def call_model(prompt):
    if not openai:
        return None
    key = os.environ.get('OPENAI_API_KEY')
    if not key:
        return None
    openai.api_key = key
    model = os.environ.get('OPENAI_MODEL', 'gpt-4o-mini')
    resp = openai.ChatCompletion.create(
        model=model,
        messages=[{"role":"system","content":prompt}],
        temperature=0.0,
        max_tokens=512
    )
    return resp['choices'][0]['message']['content']

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--question', required=True)
    args = parser.parse_args()
    docs = load_docs()
    retrieved = []
    # Try vector search if index exists
    idx_path = Path(__file__).parent / 'index.faiss'
    meta_path = Path(__file__).parent / 'metadata.json'
    if idx_path.exists() and openai:
        try:
            import numpy as np
            index = faiss.read_index(str(idx_path))
            emb_model = os.environ.get('EMBEDDING_MODEL','text-embedding-3-small')
            q_emb = openai.Embedding.create(model=emb_model, input=[args.question])['data'][0]['embedding']
            qv = np.array([q_emb]).astype('float32')
            D, I = index.search(qv, 5)
            meta = json.loads(meta_path.read_text(encoding='utf-8'))
            for idx in I[0]:
                if idx < len(meta):
                    m = meta[idx]
                    # load source doc
                    src = Path(m['source'])
                    if src.exists():
                        retrieved.append({"id": src.name, "text": src.read_text(encoding='utf-8'), "source": str(src)})
        except Exception:
            retrieved = simple_retriever(args.question, docs)
    else:
        retrieved = simple_retriever(args.question, docs)

    retrieved_docs = assemble_context(retrieved) if retrieved else ''
    prompt = build_prompt(args.question, retrieved_docs)

    answer = call_model(prompt)
    if answer:
        print('--- Model answer ---')
        print(answer)
    else:
        print('No API key or model available; showing assembled prompt and retrieved context:')
        print('\n--- PROMPT TO MODEL ---\n')
        print(prompt)

if __name__ == '__main__':
    main()
