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
    
    console.log("Ultimate Search & Replace: Removing Agent 7 everywhere...");

    // 1. Massive String Replacement in the whole object
    let wfStr = JSON.stringify(wf);
    wfStr = wfStr.replace(/7\. L'Humaniseur/g, 'Le Finaliseur');
    wfStr = wfStr.replace(/\$node\["7\. L'Humaniseur"\]/g, '$node["Le Finaliseur"]');
    wf = JSON.parse(wfStr);

    // 2. Force Connections (10 -> 10.5 -> 11)
    wf.connections["10. L'Analyste"] = {
      main: [
        [
          {
            node: "Le Finaliseur",
            type: "main",
            index: 0
          }
        ]
      ]
    };
    wf.connections["Le Finaliseur"] = {
      main: [
        [
          {
            node: "11. Le Directeur",
            type: "main",
            index: 0
          }
        ]
      ]
    };

    await request(`/workflows/${masterId}`, 'PUT', {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: {}
    });
    
    console.log("REPAIR COMPLETE. The Director is now re-educated and connected.");

  } catch (err) {
    console.error(err.message);
  }
}
main();
