"""
Query using embeddings stored in JSON and call model for answer.

Usage:
  python query_no_faiss.py --question "Cần nộp giấy tờ gì để được miễn học phí?"

This script computes embedding for the question, finds top-k similar chunks
by cosine similarity, assembles context, then calls the chat model with the prompt template.
"""
import os
import json
import argparse
import math
from pathlib import Path

try:
    import openai
    import numpy as np
except Exception:
    openai = None
    np = None

TEMPLATE = Path(__file__).parent / 'PROMPT_TEMPLATE.md'

def load_items(path):
    return json.loads(Path(path).read_text(encoding='utf-8'))

def cosine(a,b):
    if not a or not b:
        return -1
    na = math.sqrt(sum(x*x for x in a))
    nb = math.sqrt(sum(x*x for x in b))
    if na==0 or nb==0:
        return -1
    return sum(x*y for x,y in zip(a,b))/(na*nb)

def topk(query_emb, items, k=3):
    scores = []
    for it in items:
        sc = cosine(query_emb, it['embedding'])
        scores.append((sc, it))
    scores.sort(key=lambda x: x[0], reverse=True)
    return [it for s,it in scores[:k] if s>0]

def assemble_context(items):
    parts = []
    for it in items:
        header = f"[Source: {it['doc_id']} | chunk {it['chunk_index']}]\n"
        txt = it['text'].replace('\n',' ')[:1500]
        parts.append(header + txt)
    return '\n\n'.join(parts)

def build_prompt(question, context):
    if TEMPLATE.exists():
        tmpl = TEMPLATE.read_text(encoding='utf-8')
        return tmpl.replace('{user_question}', question).replace('{retrieved_docs}', context)
    return f"User: {question}\nCONTEXT:\n{context}"

def call_model(prompt):
    if not openai:
        return None
    key = os.environ.get('OPENAI_API_KEY')
    if not key:
        return None
    openai.api_key = key
    model = os.environ.get('OPENAI_MODEL','gpt-4o-mini')
    resp = openai.ChatCompletion.create(model=model, messages=[{"role":"system","content":prompt}], temperature=0)
    return resp['choices'][0]['message']['content']

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--question', required=True)
    parser.add_argument('--emb', default='scripts/rag/metadata_embeddings.json')
    parser.add_argument('--topk', type=int, default=3)
    args = parser.parse_args()

    if not Path(args.emb).exists():
        print('Embeddings file not found:', args.emb)
        return
    items = load_items(args.emb)
    key = os.environ.get('OPENAI_API_KEY')
    if not key:
        print('OPENAI_API_KEY not set in environment')
        return
    if not openai:
        print('openai package not installed')
        return

    # compute query embedding
    qemb = openai.Embedding.create(model=os.environ.get('EMBEDDING_MODEL','text-embedding-3-small'), input=[args.question])['data'][0]['embedding']
    top = topk(qemb, items, k=args.topk)
    context = assemble_context(top)
    prompt = build_prompt(args.question, context)
    ans = call_model(prompt)
    if ans:
        print('--- Answer ---')
        print(ans)
    else:
        print('No answer from model; prompt assembled:')
        print(prompt)

if __name__ == '__main__':
    main()
