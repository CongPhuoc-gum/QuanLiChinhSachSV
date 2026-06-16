"""
Prepare documents JSON for local retrieval.

This script reads txt/md files from `scripts/rag/docs/`, chunks them into
smaller passages and writes `scripts/rag/docs_chunks.json`.

No external libraries required. Run with:
  python prepare_docs_json.py
"""
from pathlib import Path
import json
import re

DOCS_DIR = Path(__file__).parent / 'docs'
OUT_PATH = Path(__file__).parent / 'docs_chunks.json'

def read_docs():
    docs = []
    for p in sorted(DOCS_DIR.glob('*.txt')):
        docs.append({'id': p.name, 'text': p.read_text(encoding='utf-8'), 'source': str(p)})
    for p in sorted(DOCS_DIR.glob('*.md')):
        docs.append({'id': p.name, 'text': p.read_text(encoding='utf-8'), 'source': str(p)})
    return docs

def normalize(text):
    # collapse whitespace
    return re.sub(r"\s+", ' ', text).strip()

def chunk_text(text, max_chars=1200, overlap=200):
    text = normalize(text)
    if len(text) <= max_chars:
        return [text]
    chunks = []
    start = 0
    L = len(text)
    while start < L:
        end = min(start + max_chars, L)
        # try to break at sentence end
        seg = text[start:end]
        last_dot = seg.rfind('. ')
        if last_dot != -1 and end < L:
            end = start + last_dot + 1
            seg = text[start:end]
        chunks.append(seg.strip())
        start = end - overlap
        if start < 0:
            start = 0
        if start >= L:
            break
    return chunks

def main():
    docs = read_docs()
    out = []
    for d in docs:
        chunks = chunk_text(d['text'])
        for i, c in enumerate(chunks):
            out.append({'doc_id': d['id'], 'source': d['source'], 'chunk_index': i, 'text': c})
    OUT_PATH.write_text(json.dumps(out, ensure_ascii=False, indent=2), encoding='utf-8')
    print(f'Wrote {len(out)} chunks to {OUT_PATH}')

if __name__ == '__main__':
    main()
