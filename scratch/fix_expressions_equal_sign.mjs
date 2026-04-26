

const N8N_URL = 'http://localhost:5678/api/v1';
const API_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJjZDBlYmZhYy1lNWViLTQ5N2YtYjI5Zi0yMzdkZTk1MjI1MzQiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwianRpIjoiNzcyYjIwZjMtMGQ2Mi00YWM4LWIxMmMtMTYzM2VhMzE1MWNlIiwiaWF0IjoxNzc2ODUxNjE2LCJleHAiOjE3ODQ2MDY0MDB9.BJoPXkyV-2bXNX7VsVjOTEfxuZrrRG_iF6SbNVFtLMQ';
const MASTER_WF_ID = 'Rbx87xuCY2i1VVBQ';

async function request(endpoint, method, payload = null) {
  const options = {
    method,
    headers: {
      'X-N8N-API-KEY': API_KEY,
      'Content-Type': 'application/json'
    }
  };
  if (payload) options.body = JSON.stringify(payload);
  const res = await fetch(`${N8N_URL}${endpoint}`, options);
  const text = await res.text();
  if (!res.ok) throw new Error(`${method} ${endpoint} failed: ${res.status} ${text}`);
  return text ? JSON.parse(text) : null;
}

async function main() {
  try {
    console.log("Fetching workflow for expression fix...");
    const wf = await request(`/workflows/${MASTER_WF_ID}`, 'GET');

    // 1. Fix Auditor URL (Add the '=' at the start for expression evaluation)
    const auditor = wf.nodes.find(n => n.name === "🕵️ 1. L'Auditeur");
    if (auditor) {
        auditor.parameters.url = "=https://kilatech.ma/api/seo-v2/audit/{{ $node[\"PRODUIT\"].json.id }}";
        console.log("Auditor URL fixed with '='.");
    }

    // 2. Fix Spy Query (Ensure '=' at the start)
    const spy = wf.nodes.find(n => n.name === "Espion");
    if (spy) {
        spy.parameters.jsonBody = "={{ JSON.stringify({ q: ($node[\"PRODUIT\"].json.name || 'Produit') + ' prix Maroc', gl: 'ma' }) }}";
        console.log("Spy query confirmed with '='.");
    }

    await request(`/workflows/${MASTER_WF_ID}`, 'PUT', {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: {}
    });

    console.log("Expression fix successful!");

  } catch (err) {
    console.error(err.message);
  }
}

main();
