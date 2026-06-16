"""
Ingest documents and save embeddings to JSON (no FAISS).

Outputs:
 - metadata_embeddings.json : list of {source, doc_id, chunk_index, text, embedding}

Usage:
  python ingest_docs_no_faiss.py --docs_dir scripts/rag/docs --out metadata_embeddings.json
"""
import os
import json
import argparse
from pathlib import Path

try:
    import openai
except Exception:
    openai = None

def read_docs(docs_dir: Path):
    docs = []
    for p in docs_dir.rglob("*.txt"):
        docs.append({"id": p.name, "text": p.read_text(encoding='utf-8'), "source": str(p)})
    for p in docs_dir.rglob("*.md"):
        docs.append({"id": p.name, "text": p.read_text(encoding='utf-8'), "source": str(p)})
    return docs

def chunk_text(text, chunk_size=1000, overlap=200):
    chunks = []
    start = 0
    L = len(text)
    while start < L:
        end = min(start + chunk_size, L)
        chunks.append(text[start:end])
        start = end - overlap
        if start < 0:
            start = 0
    return chunks

def main(docs_dir, out_path, model="text-embedding-3-small"):
    docs_dir = Path(docs_dir)
    docs = read_docs(docs_dir)
    items = []
    for d in docs:
        chunks = chunk_text(d['text'])
        for i, c in enumerate(chunks):
            items.append({"source": d['source'], "doc_id": d['id'], "chunk_index": i, "text": c})

    if not items:
        print("No documents found in", docs_dir)
        return

    if not openai:
        print('openai package not installed')
        return

    key = os.environ.get('OPENAI_API_KEY')
    if not key:
        print('OPENAI_API_KEY not set in environment')
        return
    openai.api_key = key

    embeddings = []
    batch = []
    meta_batch = []
    out = []
    for idx, it in enumerate(items):
        batch.append(it['text'])
        meta_batch.append(it)
        if len(batch) >= 50 or idx == len(items)-1:
            resp = openai.Embedding.create(model=model, input=batch)
            for i, r in enumerate(resp['data']):
                emb = r['embedding']
                entry = meta_batch[i].copy()
                entry['embedding'] = emb
                out.append(entry)
            batch = []
            meta_batch = []

    Path(out_path).parent.mkdir(parents=True, exist_ok=True)
    with open(out_path, 'w', encoding='utf-8') as f:
        json.dump(out, f, ensure_ascii=False)

    print(f'Wrote {len(out)} embedding items to {out_path}')

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--docs_dir', default='scripts/rag/docs')
    parser.add_argument('--out', default='scripts/rag/metadata_embeddings.json')
    parser.add_argument('--model', default='text-embedding-3-small')
    args = parser.parse_args()
    main(args.docs_dir, args.out, args.model)
