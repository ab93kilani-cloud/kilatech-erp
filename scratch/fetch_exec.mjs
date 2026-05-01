import fs from 'fs';

const N8N_URL = 'http://localhost:5678/api/v1';
const API_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJjZDBlYmZhYy1lNWViLTQ5N2YtYjI5Zi0yMzdkZTk1MjI1MzQiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwianRpIjoiNzcyYjIwZjMtMGQ2Mi00YWM4LWIxMmMtMTYzM2VhMzE1MWNlIiwiaWF0IjoxNzc2ODUxNjE2LCJleHAiOjE3ODQ2MDY0MDB9.BJoPXkyV-2bXNX7VsVjOTEfxuZrrRG_iF6SbNVFtLMQ';
const masterId = "Rbx87xuCY2i1VVBQ";

async function fetchExecution() {
  const options = {
    method: 'GET',
    headers: { 'X-N8N-API-KEY': API_KEY, 'Content-Type': 'application/json' }
  };
  const res = await fetch(`${N8N_URL}/executions?limit=1&workflowId=${masterId}`, options);
  const data = await res.json();
  
  if (data && data.data && data.data.length > 0) {
      const execId = data.data[0].id;
      const res2 = await fetch(`${N8N_URL}/executions/${execId}`, options);
      const fullExec = await res2.json();
      fs.writeFileSync('scratch/last_exec.json', JSON.stringify(fullExec, null, 2));
      console.log('Execution data saved to scratch/last_exec.json');
  } else {
      console.log('No executions found.');
  }
}

fetchExecution().catch(console.error);
