import fs from 'fs';
const N8N_URL = 'http://localhost:5678/api/v1';
const API_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJjZDBlYmZhYy1lNWViLTQ5N2YtYjI5Zi0yMzdkZTk1MjI1MzQiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwianRpIjoiNzcyYjIwZjMtMGQ2Mi00YWM4LWIxMmMtMTYzM2VhMzE1MWNlIiwiaWF0IjoxNzc2ODUxNjE2LCJleHAiOjE3ODQ2MDY0MDB9.BJoPXkyV-2bXNX7VsVjOTEfxuZrrRG_iF6SbNVFtLMQ';
const masterId = "Rbx87xuCY2i1VVBQ";

async function request(endpoint, method, payload = null) {
  const options = {
    method,
    headers: { 'X-N8N-API-KEY': API_KEY, 'Content-Type': 'application/json' }
  };
  if (payload) options.body = JSON.stringify(payload);
  const res = await fetch(`${N8N_URL}${endpoint}`, options);
  const text = await res.text();
  if (!res.ok) throw new Error(`${method} ${endpoint} failed: ${res.status} ${text}`);
  return text ? JSON.parse(text) : null;
}

async function inspect() {
  const wf = await request(`/workflows/${masterId}`, 'GET');
  fs.writeFileSync('scratch/workflow_nodes.json', JSON.stringify(wf.nodes, null, 2));
  console.log(wf.nodes.map(n => n.name));
}

inspect().catch(console.error);
