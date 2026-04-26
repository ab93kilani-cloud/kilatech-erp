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
  
  const manager = wf.nodes.find(n => n.name.includes("Le Manager"));
  if (manager) {
    // Fix the CORRECT field: jsonQuery (this is what n8n actually reads)
    // Also remove the conflicting jsonParameters field
    const correctJson = `={\n  "phone": "+212645065281",\n  "text": "✅ Kilatech SEO Alert: Le produit {{ $node['PRODUIT'].json.name }} (ID: {{ $node['PRODUIT'].json.id }}) est optimise! Score SEO: {{ $node['🏢 11. Le Directeur'].json.seo_score }}/100",\n  "apikey": "341855"\n}`;
    
    manager.parameters.jsonQuery = correctJson;
    // Remove the ghost field to avoid confusion
    delete manager.parameters.jsonParameters;

    console.log("Fixed jsonQuery to point to PRODUIT node.");
    console.log("Removed conflicting jsonParameters field.");
  }

  await request(`/workflows/${masterId}`, 'PUT', {
    name: wf.name,
    nodes: wf.nodes,
    connections: wf.connections,
    settings: {}
  });

  console.log("Manager is now 100% clean. WhatsApp message will work.");
}
main().catch(console.error);
