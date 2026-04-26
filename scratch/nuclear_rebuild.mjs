import fs from 'fs';

const N8N_URL = 'http://localhost:5678/api/v1';
const API_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJjZDBlYmZhYy1lNWViLTQ5N2YtYjI5Zi0yMzdkZTk1MjI1MzQiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwianRpIjoiNzcyYjIwZjMtMGQ2Mi00YWM4LWIxMmMtMTYzM2VhMzE1MWNlIiwiaWF0IjoxNzc2ODUxNjE2LCJleHAiOjE3ODQ2MDY0MDB9.BJoPXkyV-2bXNX7VsVjOTEfxuZrrRG_iF6SbNVFtLMQ';

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
    const masterId = "Rbx87xuCY2i1VVBQ";
    let wf = await request(`/workflows/${masterId}`, 'GET');
    
    console.log("NUCLEAR PURGE: Rebuilding all connections from scratch...");

    // 1. Wipe all connections
    wf.connections = {};

    // 2. Define the Perfect Path (Explicit mapping)
    const path = [
        "1. L'Auditeur",
        "Extract Data Array",
        "PRODUIT",
        "L'Espion",
        "Aggregator",
        "Architect",
        "Redacteur",
        "5. Le Designer",
        "6. Le Tisseur",
        "8. Le Rédacteur en Chef",
        "9. Le CM",
        "10. L'Analyste",
        "Le Finaliseur",
        "11. Le Directeur",
        "Le Manager"
    ];

    // 3. Rebuild connections strictly
    for (let i = 0; i < path.length - 1; i++) {
        const source = path[i];
        const target = path[i + 1];
        
        // Check if nodes exist before connecting
        if (wf.nodes.find(n => n.name === source) && wf.nodes.find(n => n.name === target)) {
            wf.connections[source] = {
                main: [
                    [
                        {
                            node: target,
                            type: "main",
                            index: 0
                        }
                    ]
                ]
            };
        } else {
            console.warn(`Warning: Missing node ${source} or ${target} in the path.`);
        }
    }

    await request(`/workflows/${masterId}`, 'PUT', {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: {}
    });
    
    console.log("RECONSTRUCTION SUCCESSFUL. All ghost residues are gone.");

  } catch (err) {
    console.error(err.message);
  }
}
main();
