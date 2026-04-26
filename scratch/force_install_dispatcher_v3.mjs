

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
    console.log("Forcing Team Dispatcher installation (V3)...");
    const wf = await request(`/workflows/${MASTER_WF_ID}`, 'GET');

    const architectName = "Architect";
    const dispatcherName = "LE DISPATCHER";

    // 1. Clean existing nodes and connections related to Dispatcher
    wf.nodes = wf.nodes.filter(n => n.name !== dispatcherName);

    // 2. Add Dispatcher Node
    const dispatcherNode = {
      parameters: {
        jsCode: "return $input.all();"
      },
      name: dispatcherName,
      type: "n8n-nodes-base.code",
      typeVersion: 2,
      position: [780, 150]
    };
    wf.nodes.push(dispatcherNode);

    // 3. Rewire Architect -> Dispatcher
    wf.connections[architectName] = {
        main: [
            [
                {
                    node: dispatcherName,
                    type: "main",
                    index: 0
                }
            ]
        ]
    };

    // 4. Rewire Dispatcher -> Team
    const teamAgents = ["Redacteur", "🎨 5. Le Designer", "🔗 6. Le Tisseur", "⚖️ 8. Le Rédacteur en Chef", "📊 10. L'Analyste", "📢 9. Le CM"];
    wf.connections[dispatcherName] = {
        main: [
            teamAgents.map(agent => ({
                node: agent,
                type: "main",
                index: 0
            }))
        ]
    };

    // 5. Clean update payload to avoid 400 error
    await request(`/workflows/${MASTER_WF_ID}`, 'PUT', {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: {
          executionOrder: "v1"
      }
    });

    console.log("LE DISPATCHER IS NOW LIVE! (Please refresh n8n page)");

  } catch (err) {
    console.error(err.message);
  }
}

main();
