

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
    console.log("Fetching workflow for Merge upgrade...");
    const wf = await request(`/workflows/${MASTER_WF_ID}`, 'GET');

    const auditorName = "🕵️ 1. L'Auditeur";
    const espionName = "Espion";
    const aggregatorName = "Aggregator";
    const architectName = "Architect";

    // 1. Remove the old Aggregator Code Node
    wf.nodes = wf.nodes.filter(n => n.name !== aggregatorName);

    // 2. Create the new Merge Node
    const mergeNode = {
      parameters: {
        mode: "combine",
        combinationMode: "mergeByPosition"
      },
      name: "Fusionneur Intelligent",
      type: "n8n-nodes-base.merge",
      typeVersion: 2.1,
      position: [480, 128],
      id: "merge-node-id-unique"
    };
    wf.nodes.push(mergeNode);

    // 3. Reconnect everything to the Merge Node
    // Auditor -> Merge
    wf.connections[auditorName].main[0] = [
        {
            node: mergeNode.name,
            type: "main",
            index: 0
        }
    ];

    // Espion -> Merge
    wf.connections[espionName].main[0] = [
        {
            node: mergeNode.name,
            type: "main",
            index: 1 // Input 2 of Merge
        }
    ];

    // Merge -> Architect
    wf.connections[mergeNode.name] = {
        main: [
            [
                {
                    node: architectName,
                    type: "main",
                    index: 0
                }
            ]
        ]
    };

    // Remove the old Aggregator from connections
    delete wf.connections[aggregatorName];

    await request(`/workflows/${MASTER_WF_ID}`, 'PUT', {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: {}
    });

    console.log("Workflow upgraded to use a robust Merge node!");

  } catch (err) {
    console.error(err.message);
  }
}

main();
