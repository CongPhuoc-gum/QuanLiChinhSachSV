"""
Local retriever using simple TF overlap scoring.

Usage:
  python local_retriever.py --question "Cần nộp giấy tờ gì?"

This does NOT call any external API. It prints the assembled prompt ready
to paste into a model or for manual copy to CTSV.
"""
import argparse
from pathlib import Path
import json
import re

CHUNKS_PATH = Path(__file__).parent / 'docs_chunks.json'

def load_chunks():
    if not CHUNKS_PATH.exists():
        raise SystemExit('Run prepare_docs_json.py first to create docs_chunks.json')
    return json.loads(CHUNKS_PATH.read_text(encoding='utf-8'))

def score(query, text):
    qset = set(re.findall(r"\w+", query.lower()))
    tset = set(re.findall(r"\w+", text.lower()))
    return len(qset & tset)

def top_k(query, chunks, k=3):
    scored = [(score(query, c['text']), c) for c in chunks]
    scored.sort(key=lambda x: x[0], reverse=True)
    return [c for s,c in scored[:k] if s>0]

def assemble_prompt(question, retrieved):
    header = 'System: Bạn là trợ lý chuyên môn về chính sách học phí. Trả lời ngắn gọn, dẫn nguồn.\n'
    context = '\n\n'.join([f"[Source: {r['doc_id']}] {r['text'][:800]}" for r in retrieved])
    prompt = f"{header}\nUser: {question}\nCONTEXT:\n{context}\n\nAnswer:" 
    return prompt

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--question', required=True)
    args = parser.parse_args()
    chunks = load_chunks()
    retrieved = top_k(args.question, chunks, k=4)
    if not retrieved:
        print('No relevant passages found. Provide more detail or attach documents.')
        return
    prompt = assemble_prompt(args.question, retrieved)
    print('\n--- ASSEMBLED PROMPT (copy to model) ---\n')
    print(prompt)

if __name__ == '__main__':
    main()
