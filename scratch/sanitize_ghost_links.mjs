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
    
    console.log("Deep sanitizing workflow connections...");

    // Create a set of all existing node names
    const existingNodes = new Set(wf.nodes.map(n => n.name));

    // Sanitize connections
    const cleanConnections = {};
    for (const [sourceNode, targets] of Object.entries(wf.connections)) {
        if (!existingNodes.has(sourceNode)) {
            console.log(`Removing orphan source: ${sourceNode}`);
            continue;
        }

        const validMainTargets = (targets.main || []).map(branch => {
            return branch.filter(target => {
                const exists = existingNodes.has(target.node);
                if (!exists) console.log(`Removing orphan connection from ${sourceNode} to ${target.node}`);
                return exists;
            });
        });

        cleanConnections[sourceNode] = { main: validMainTargets };
    }

    wf.connections = cleanConnections;

    await request(`/workflows/${masterId}`, 'PUT', {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: {}
    });
    
    console.log("Sanitization complete. Ghost connections removed.");

  } catch (err) {
    console.error(err.message);
  }
}
main();
