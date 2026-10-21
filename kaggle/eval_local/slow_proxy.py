#!/usr/bin/env python3
"""Relais « vitesse de l'évaluateur » entre le harnais et vLLM (VM Colab).

Chaque réponse est retenue jusqu'à ce que son temps total atteigne le coût qu'elle aurait sur l'évaluateur Kaggle
(4x L4) : PER_CALL + tokens générés (réflexion comprise) / TOK_S — modèle mesuré par gemma4-swe-kit
(https://github.com/damsolanke/gemma4-swe-kit, `g4kit-scorer-time` : 0,6 s + tokens / 25,5 tok/s).
Le harnais voit donc le vrai temps de l'évaluateur : max_time_minutes coupe aux mêmes moments que sur Kaggle.
Réponses en flux (SSE) : mises en tampon, comptées (usage si présent, sinon un token par fragment), puis renvoyées.
Journal JSONL : une ligne par appel (tokens, temps A100, temps simulé). --dump : contextes d'édition pour replay_edits.py.
Usage : python3 slow_proxy.py --listen 8001 --upstream http://127.0.0.1:8000 --log slow_proxy.jsonl
"""
import argparse, http.client, json, threading, time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import urlparse

A = None
LOCK = threading.Lock()


def count_tokens(body: bytes, streamed: bool) -> int:
    if not streamed:
        try:
            return int(json.loads(body)["usage"]["completion_tokens"])
        except Exception:
            return 0
    n, usage = 0, None
    for line in body.split(b"\n"):
        if not line.startswith(b"data: ") or line.strip() == b"data: [DONE]":
            continue
        try:
            d = json.loads(line[6:])
        except Exception:
            continue
        if d.get("usage") and d["usage"].get("completion_tokens"):
            usage = d["usage"]["completion_tokens"]
        for c in d.get("choices") or []:
            delta = c.get("delta") or {}
            if delta.get("content") or delta.get("reasoning") or delta.get("reasoning_content") or delta.get("tool_calls"):
                n += 1
    return int(usage or n)


class H(BaseHTTPRequestHandler):
    protocol_version = "HTTP/1.1"

    def log_message(self, *a):
        pass

    def _forward(self, method):
        t0 = time.monotonic()
        length = int(self.headers.get("Content-Length") or 0)
        req = self.rfile.read(length) if length else None
        streamed = False
        if req and self.path.endswith("/chat/completions"):
            try:
                streamed = bool(json.loads(req).get("stream"))
            except Exception:
                pass
        up = urlparse(A.upstream)
        conn = http.client.HTTPConnection(up.hostname, up.port, timeout=3600)
        headers = {k: v for k, v in self.headers.items() if k.lower() not in ("host", "content-length", "connection")}
        conn.request(method, self.path, body=req, headers=headers)
        r = conn.getresponse()
        body = r.read()
        conn.close()
        if self.path.endswith("/chat/completions") and r.status == 200:
            toks = count_tokens(body, streamed)
            target = A.per_call + toks / A.tok_s
            local = time.monotonic() - t0
            if target > local:
                time.sleep(target - local)
            if A.dump and not streamed:
                try:
                    msg = json.loads(body)["choices"][0]["message"]
                    names = [c["function"]["name"] for c in msg.get("tool_calls") or []]
                    if any(n in ("edit_file", "write_file") for n in names):
                        with LOCK, open(A.dump, "a") as f:
                            f.write(json.dumps({"request": json.loads(req), "response": msg}) + "\n")
                except Exception:
                    pass
            with LOCK, open(A.log, "a") as f:
                f.write(json.dumps({"t": time.time(), "tokens": toks, "local_s": round(local, 2),
                                    "simulated_s": round(max(target, local), 2), "stream": streamed}) + "\n")
        self.send_response(r.status)
        for k, v in r.getheaders():
            if k.lower() not in ("content-length", "transfer-encoding", "connection"):
                self.send_header(k, v)
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        self._forward("GET")

    def do_POST(self):
        self._forward("POST")


def main():
    global A
    p = argparse.ArgumentParser()
    p.add_argument("--listen", type=int, default=8001)
    p.add_argument("--upstream", default="http://127.0.0.1:8000")
    p.add_argument("--per-call", type=float, default=0.6)
    p.add_argument("--tok-s", type=float, default=25.5)
    p.add_argument("--log", default="slow_proxy.jsonl")
    p.add_argument("--dump", default="", help="JSONL des requêtes dont la réponse appelle edit_file/write_file (pour le rejeu)")
    A = p.parse_args()
    ThreadingHTTPServer(("127.0.0.1", A.listen), H).serve_forever()


if __name__ == "__main__":
    main()
