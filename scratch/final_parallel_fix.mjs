

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
    console.log("Fetching workflow for final repair...");
    const wf = await request(`/workflows/${MASTER_WF_ID}`, 'GET');

    // 1. Fix Auditor
    const auditor = wf.nodes.find(n => n.name === "🕵️ 1. L'Auditeur");
    if (auditor) {
        auditor.parameters.url = "https://kilatech.ma/api/seo-v2/audit/{{ $node[\"PRODUIT\"].json.id }}";
    }

    // 2. Fix Spy
    const spy = wf.nodes.find(n => n.name === "Espion");
    if (spy) {
        spy.parameters.jsonBody = "={{ JSON.stringify({ q: ($node[\"PRODUIT\"].json.name || 'Produit') + ' prix Maroc', gl: 'ma' }) }}";
    }

    // 3. Fix Aggregator Code to be even more robust
    const aggregator = wf.nodes.find(n => n.name === "Aggregator");
    if (aggregator) {
        aggregator.parameters.jsCode = `
// Access data explicitly from branches
const productData = $node["PRODUIT"].json;
const spyData = $node["Espion"].json;
const auditorData = $node["🕵️ 1. L'Auditeur"].json;

const google = spyData.organic || [];
const topResults = google.slice(0, 3).map(res => ({
  titre: res.title,
  resume: res.snippet
}));

return [{
  json: {
    ...productData,
    audit_technique: auditorData.text || auditorData,
    infos_marche: topResults
  }
}];`;
    }

    await request(`/workflows/${MASTER_WF_ID}`, 'PUT', {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: {}
    });

    console.log("Final repair successful! Parallel logic is now bulletproof.");

  } catch (err) {
    console.error(err.message);
  }
}

main();
