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
    
    console.log("Repairing L'Analyste: Setting to Text mode and SEO Audit tasks...");

    const analystNode = wf.nodes.find(n => n.name.includes("Analyste"));
    if (analystNode) {
        // Change from Chat to Text
        analystNode.parameters.resource = "text";
        analystNode.parameters.operation = "complete";
        analystNode.parameters.model = "gpt-3.5-turbo-instruct";
        
        analystNode.parameters.prompt = "=Tu es l'Analyste SEO Senior pour Kilatech.ma.\\n\\nVOICI LE CONTENU À ANALYSER :\\n{{ $json.text }}\\n\\nMISSION D'AUDIT :\\n1. SCORE SEO : Note le contenu sur 100.\\n2. POINTS FORTS : Cite 2 points forts.\\n3. AMÉLIORATION : Cite 1 point à améliorer.\\n4. INTÉGRITÉ : Recopie ensuite 100% du texte HTML final sans rien changer.\\n\\nPRÉSENTE TON RAPPORT D'AUDIT AU DÉBUT, PUIS LE TEXTE INTÉGRAL.";
        
        analystNode.parameters.options = analystNode.parameters.options || {};
        analystNode.parameters.options.maxTokens = 3000;
    }

    await request(`/workflows/${masterId}`, 'PUT', {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: {}
    });
    
    console.log("The Analyst is now auditing!");

  } catch (err) {
    console.error(err.message);
  }
}
main();
