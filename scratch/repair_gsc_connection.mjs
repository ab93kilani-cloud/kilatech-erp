

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

    // Fix connection: GSC - Get Products (ERP) -> Extract Data Array
    if (!wf.connections["GSC - Get Products (ERP)"]) {
        wf.connections["GSC - Get Products (ERP)"] = { main: [[]] };
    }
    
    wf.connections["GSC - Get Products (ERP)"].main[0] = [
        {
            node: "Extract Data Array",
            type: "main",
            index: 0
        }
    ];

    console.log("Connection GSC -> Extract Data Array restored.");

    // Save the workflow
    const payload = {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: {}
    };

    await request(`/workflows/${MASTER_WF_ID}`, 'PUT', payload);
    console.log("Workflow successfully repaired!");

  } catch (err) {
    console.error("Error:", err.message);
  }
}

main();
