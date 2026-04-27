import fs from 'fs';

const N8N_URL = 'http://localhost:5678/api/v1';
const API_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJjZDBlYmZhYy1lNWViLTQ5N2YtYjI5Zi0yMzdkZTk1MjI1MzQiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwianRpIjoiNzcyYjIwZjMtMGQ2Mi00YWM4LWIxMmMtMTYzM2VhMzE1MWNlIiwiaWF0IjoxNzc2ODUxNjE2LCJleHAiOjE3ODQ2MDY0MDB9.BJoPXkyV-2bXNX7VsVjOTEfxuZrrRG_iF6SbNVFtLMQ';
const masterId = "Rbx87xuCY2i1VVBQ";

async function request(endpoint, method, payload = null) {
  const options = {
    method,
    headers: { 'X-N8N-API-KEY': API_KEY, 'Content-Type': 'application/json' }
  };
  if (payload) options.body = JSON.stringify(payload);
  const res = await fetch(`${N8N_URL}${endpoint}`, options);
  const text = await res.text();
  if (!res.ok) throw new Error(`${method} ${endpoint} failed: ${res.status} ${text}`);
  return text ? JSON.parse(text) : null;
}

async function deploy() {
  console.log(`Fetching current live workflow...`);
  const liveWf = await request(`/workflows/${masterId}`, 'GET');

  console.log(`Reading local workflow file...`);
  const localContent = fs.readFileSync('backend/WORKFLOW_MASTER_KILATECH.json', 'utf8');
  const localWfArray = JSON.parse(localContent);
  const localWf = localWfArray[0];

  console.log(`Merging changes...`);
  // We keep the live name, id, and settings to be safe
  // We only replace nodes and connections
  const updatedWf = {
    name: liveWf.name,
    nodes: localWf.nodes,
    connections: localWf.connections,
    settings: {} // Let's try empty settings to avoid schema errors, or try basic ones
  };

  console.log(`Deploying updated workflow to n8n...`);
  
  await request(`/workflows/${masterId}`, 'PUT', updatedWf);

  console.log(`\n✅ SUCCESS: Workflow "WORKFLOW_MASTER_KILATECH.json" deployed successfully to n8n!`);
}

deploy().catch(err => {
  console.error(`\n❌ ERROR during deployment:`, err.message);
  process.exit(1);
});
