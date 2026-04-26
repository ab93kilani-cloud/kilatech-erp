const N8N_URL = 'http://localhost:5678/api/v1';
const API_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJjZDBlYmZhYy1lNWViLTQ5N2YtYjI5Zi0yMzdkZTk1MjI1MzQiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwianRpIjoiNzcyYjIwZjMtMGQ2Mi00YWM4LWIxMmMtMTYzM2VhMzE1MWNlIiwiaWF0IjoxNzc2ODUxNjE2LCJleHAiOjE3ODQ2MDY0MDB9.BJoPXkyV-2bXNX7VsVjOTEfxuZrrRG_iF6SbNVFtLMQ';

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

async function main() {
  const masterId = "Rbx87xuCY2i1VVBQ";
  const wf = await request(`/workflows/${masterId}`, 'GET');
  
  const director = wf.nodes.find(n => n.name.includes("Le Directeur"));
  if (director) {
    // 1. Clean URL (Remove token from query string)
    director.parameters.url = "=https://kilatech.ma/api/seo-v2/update/{{ $node[\"PRODUIT\"].json.id }}";
    
    // 2. Add Security Header
    director.parameters.sendHeaders = true;
    director.parameters.headerParametersUi = {
      parameter: [
        { name: "X-SEO-TOKEN", value: "KILATECH_7382" },
        { name: "Accept", value: "application/json" }
      ]
    };
    
    // 3. Keep body parameters
    console.log("✅ Director updated: Cleaner URL + Security Header added.");
  }

  await request(`/workflows/${masterId}`, 'PUT', {
    name: wf.name,
    nodes: wf.nodes,
    connections: wf.connections,
    settings: {}
  });

  console.log("n8n node is now configured for high-priority injection.");
}
main().catch(console.error);
