Quick RAG ingestion guide

Steps to use:

1. Place source documents (txt/md) into `scripts/rag/docs/`.
2. Create a Python virtualenv and install dependencies. Example:

```bash
python -m venv .venv
source .venv/bin/activate    # on Windows: .venv\Scripts\activate
pip install openai faiss-cpu tiktoken python-dotenv tqdm
```

3. Export `OPENAI_API_KEY` or put it in a `.env` file.

4. Run ingestion:

```bash
python ingest_docs.py --docs_dir scripts/rag/docs
```

Outputs:
- `scripts/rag/index.faiss` : FAISS index
- `scripts/rag/metadata.json` : metadata for each vector (source, chunk_index)

Notes:
- This is a minimal starter. For production, add PDF parsing, better chunking, concurrency,
  and a persistent vector DB (Pinecone, Milvus, Weaviate) if needed.
