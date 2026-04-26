const N8N_URL = 'http://localhost:5678/api/v1';
const API_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJjZDBlYmZhYy1lNWViLTQ5N2YtYjI5Zi0yMzdkZTk1MjI1MzQiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwianRpIjoiNzcyYjIwZjMtMGQ2Mi00YWM4LWIxMmMtMTYzM2VhMzE1MWNlIiwiaWF0IjoxNzc2ODUxNjE2LCJleHAiOjE3ODQ2MDY0MDB9.BJoPXkyV-2bXNX7VsVjOTEfxuZrrRG_iF6SbNVFtLMQ';

async function request(endpoint, method, payload = null) {
  const opts = { method, headers: { 'X-N8N-API-KEY': API_KEY, 'Content-Type': 'application/json' } };
  if (payload) opts.body = JSON.stringify(payload);
  const res = await fetch(`${N8N_URL}${endpoint}`, opts);
  const text = await res.text();
  if (!res.ok) throw new Error(`${res.status}: ${text}`);
  return text ? JSON.parse(text) : null;
}

async function main() {
  const wf = await request('/workflows/Rbx87xuCY2i1VVBQ', 'GET');
  const director = wf.nodes.find(n => n.name.includes('Directeur'));
  
  if (!director) { console.error('Directeur node not found!'); return; }
  
  console.log('Found node:', director.name);

  // THE DEFINITIVE FIX:
  // Token embedded DIRECTLY in the URL (most reliable method, no headers needed)
  // n8n expression with = prefix to enable expression mode
  director.parameters.url = '=https://kilatech.ma/api/seo-v2/update/{{$node["PRODUIT"].json.id}}?api_token=KILATECH_7382';
  
  // Disable headers to avoid confusion (token is in URL, that's enough)
  director.parameters.sendHeaders = false;
  director.parameters.sendQueryParameters = false;
  
  console.log('✅ URL set to:', director.parameters.url);

  await request('/workflows/Rbx87xuCY2i1VVBQ', 'PUT', {
    name: wf.name,
    nodes: wf.nodes,
    connections: wf.connections,
    settings: {}
  });

  console.log('🎯 DEFINITIVE FIX APPLIED. Token is now permanently in the URL.');
}

main().catch(console.error);
