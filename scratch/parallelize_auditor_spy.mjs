

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
    console.log("Fetching current workflow...");
    const wf = await request(`/workflows/${MASTER_WF_ID}`, 'GET');

    // 1. Rewire PRODUIT -> L'Auditeur AND PRODUIT -> Espion
    if (!wf.connections["PRODUIT"]) {
        wf.connections["PRODUIT"] = { main: [[]] };
    }
    
    // Ensure both are targets of PRODUIT
    wf.connections["PRODUIT"].main[0] = [
        {
            node: "1. L'Auditeur",
            type: "main",
            index: 0
        },
        {
            node: "Espion",
            type: "main",
            index: 0
        }
    ];

    // 2. Remove L'Auditeur -> Espion link
    if (wf.connections["1. L'Auditeur"]) {
        wf.connections["1. L'Auditeur"].main[0] = wf.connections["1. L'Auditeur"].main[0].filter(c => c.node !== "Espion");
    }

    // 3. Ensure L'Auditeur -> Aggregator exists
    if (!wf.connections["1. L'Auditeur"].main[0].find(c => c.node === "Aggregator")) {
        wf.connections["1. L'Auditeur"].main[0].push({
            node: "Aggregator",
            type: "main",
            index: 0
        });
    }

    // 4. Ensure Espion -> Aggregator exists
    if (!wf.connections["Espion"]) {
        wf.connections["Espion"] = { main: [[]] };
    }
    wf.connections["Espion"].main[0] = [
        {
            node: "Aggregator",
            type: "main",
            index: 0
        }
    ];

    console.log("Rewired to parallel branches.");

    // Save the workflow
    const payload = {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: {}
    };

    await request(`/workflows/${MASTER_WF_ID}`, 'PUT', payload);
    console.log("Workflow successfully rewired to Parallel Mode!");

  } catch (err) {
    console.error("Error:", err.message);
  }
}

main();
