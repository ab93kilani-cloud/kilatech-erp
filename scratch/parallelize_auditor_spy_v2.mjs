

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

    const auditorName = "🕵️ 1. L'Auditeur";
    const espionName = "Espion";
    const aggregatorName = "Aggregator";
    const produitName = "PRODUIT";

    // 1. Rewire PRODUIT (Loop branch, index 1) -> Auditor AND Spy
    if (wf.connections[produitName]) {
        wf.connections[produitName].main[1] = [
            {
                node: auditorName,
                type: "main",
                index: 0
            },
            {
                node: espionName,
                type: "main",
                index: 0
            }
        ];
    }

    // 2. Fix Auditor connections: Remove link to Spy, keep/add link to Aggregator
    if (wf.connections[auditorName]) {
        wf.connections[auditorName].main[0] = wf.connections[auditorName].main[0].filter(c => c.node !== espionName);
        if (!wf.connections[auditorName].main[0].find(c => c.node === aggregatorName)) {
            wf.connections[auditorName].main[0].push({
                node: aggregatorName,
                type: "main",
                index: 0
            });
        }
    }

    // 3. Fix Spy connections: Link to Aggregator
    if (!wf.connections[espionName]) {
        wf.connections[espionName] = { main: [[]] };
    }
    wf.connections[espionName].main[0] = [
        {
            node: aggregatorName,
            type: "main",
            index: 0
        }
    ];

    console.log("Rewiring completed in memory.");

    // Save the workflow
    const payload = {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: {}
    };

    await request(`/workflows/${MASTER_WF_ID}`, 'PUT', payload);
    console.log("Workflow successfully parallelized with correct node names!");

  } catch (err) {
    console.error("Error:", err.message);
  }
}

main();
