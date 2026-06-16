"""
Simple ingestion script: read files from a docs folder, chunk text, create embeddings via OpenAI,
and build a FAISS index with metadata saved to JSON.

Usage:
  - Put your documents (pdf/tx/markdown/txt) into `scripts/rag/docs/`.
  - Set `OPENAI_API_KEY` in your environment or a .env file.
  - Install requirements: `pip install -r requirements.txt` (see README).
  - Run: `python ingest_docs.py --docs_dir scripts/rag/docs`
"""
import os
import json
import argparse
from pathlib import Path
from typing import List

try:
    import openai
    import tiktoken
    import faiss
except Exception:
    pass

def read_text_files(docs_dir: Path) -> List[dict]:
    docs = []
    for p in docs_dir.rglob("*.txt"):
        text = p.read_text(encoding="utf-8")
        docs.append({"id": str(p.relative_to(docs_dir)), "text": text, "source": str(p)})
    for p in docs_dir.rglob("*.md"):
        text = p.read_text(encoding="utf-8")
        docs.append({"id": str(p.relative_to(docs_dir)), "text": text, "source": str(p)})
    return docs

def chunk_text(text: str, chunk_size: int = 1000, overlap: int = 200):
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

def main(docs_dir: str, out_index: str, out_meta: str, openai_model: str = "text-embedding-3-small"):
    docs_dir = Path(docs_dir)
    docs = read_text_files(docs_dir)
    all_texts = []
    metadata = []
    for d in docs:
        chunks = chunk_text(d["text"]) if d["text"] else []
        for i, c in enumerate(chunks):
            all_texts.append(c)
            metadata.append({"source": d["source"], "doc_id": d["id"], "chunk_index": i})

    if not all_texts:
        print("No text files found in", docs_dir)
        return

    # Create embeddings in batches
    openai.api_key = os.environ.get("OPENAI_API_KEY")
    batch_size = 50
    embeddings = []
    for i in range(0, len(all_texts), batch_size):
        batch = all_texts[i:i+batch_size]
        resp = openai.Embedding.create(model=openai_model, input=batch)
        for item in resp["data"]:
            embeddings.append(item["embedding"])

    import numpy as np
    vecs = np.array(embeddings).astype('float32')
    d = vecs.shape[1]

    index = faiss.IndexFlatL2(d)
    index.add(vecs)
    faiss.write_index(index, out_index)

    with open(out_meta, "w", encoding="utf-8") as f:
        json.dump(metadata, f, ensure_ascii=False, indent=2)

    print(f"Wrote index {out_index} ({len(embeddings)} vectors) and metadata {out_meta}")

if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--docs_dir", default="scripts/rag/docs")
    parser.add_argument("--out_index", default="scripts/rag/index.faiss")
    parser.add_argument("--out_meta", default="scripts/rag/metadata.json")
    args = parser.parse_args()
    main(args.docs_dir, args.out_index, args.out_meta)
