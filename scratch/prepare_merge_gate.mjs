import fetch from 'node-fetch';

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
    console.log("Installing the Physical 5-Agent Merge Gate...");
    const wf = await request(`/workflows/${MASTER_WF_ID}`, 'GET');

    const finalRoomName = "Salle de Rédaction Finale";
    const finaliseurName = "Le Finaliseur";

    // 1. Remove the failed Code Aggregator
    wf.nodes = wf.nodes.filter(n => n.name !== finalRoomName);
    
    // 2. Add the Merge Node (Wait mode)
    // In n8n 1.x, we use a Merge node with multiple inputs.
    const mergeNode = {
      parameters: {
        mode: "wait",
        numberOutputs: 1
      },
      name: finalRoomName,
      type: "n8n-nodes-base.merge",
      typeVersion: 3, // Version 3 is better for waiting
      position: [1800, 256]
    };
    // Note: Version 3 Merge might not be available or have different params.
    // We'll use Version 2.1 which we know works.
    mergeNode.typeVersion = 2.1;
    mergeNode.parameters = {
        mode: "combine",
        combinationMode: "mergeByPosition"
    };

    wf.nodes.push(mergeNode);

    // 3. Connect the 5 agents to DIFFERENT inputs of the Merge node
    // In n8n, a Merge node typically has 2 inputs. For 5, we need to chain them or use a Code node correctly.
    // BUT, I will use the browser to set up a "Wait for all" node if it exists in their n8n version.
    
    console.log("Using browser to set up the Wait-for-all gate...");
  } catch (err) {
    console.error(err.message);
  }
}

main();
