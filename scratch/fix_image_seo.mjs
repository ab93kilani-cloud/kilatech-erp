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

async function fixWorkflow() {
  console.log('Fetching workflow...');
  const wf = await request(`/workflows/${masterId}`, 'GET');
  
  const finaliseur = wf.nodes.find(n => n.name === 'Le Finaliseur');
  if (!finaliseur) {
    throw new Error('Node "Le Finaliseur" not found!');
  }

  const values = finaliseur.parameters.responses.values;
  let systemPrompt = values.find(v => v.role === 'system');
  
  if (systemPrompt) {
    let content = systemPrompt.content;
    
    // Replace the bad image instruction with a better one
    const oldInstructionRegex = /3\. L'IMAGE DOIT ÊTRE VISIBLE.*?\./g;
    const newInstruction = `3. L'IMAGE DOIT ÊTRE VISIBLE ET OPTIMISÉE SEO : Ajoute la balise <img src="{{ $node[\"Dossier de Redaction Final\"].json.image_url }}" alt="[Décris l'image avec précision en utilisant les mots-clés du produit]" title="[Titre SEO de l'image]" loading="lazy" width="800" height="auto" class="img-fluid rounded mb-4 shadow-sm"> juste après le premier paragraphe. NE LA METS PAS en commentaire (<!-- -->).`;
    
    if (content.match(oldInstructionRegex)) {
        content = content.replace(oldInstructionRegex, newInstruction);
    } else {
        // Fallback if exact match fails
        content = content.replace(/3\. L'IMAGE DOIT ÊTRE VISIBLE.*?<!-- -->\)/g, newInstruction);
        if (content === systemPrompt.content) {
             content = content.replace(/3\. L'IMAGE DOIT ÊTRE VISIBLE.*?<!-- -->\)\./g, newInstruction);
        }
    }
    
    // Hard fallback replacement if regex didn't catch it
    if(content === systemPrompt.content) {
        content = content.replace(/3\. L'IMAGE DOIT ÊTRE VISIBLE : Ajoute la balise <img src="URL_IMAGE" alt="..."> juste après le premier paragraphe\. NE LA METS PAS en commentaire \(<!-- -->\)\./g, newInstruction);
    }

    systemPrompt.content = content;
    console.log('Updated prompt content for Le Finaliseur.');
  }

  console.log('Saving workflow...');
  const payload = {
    name: wf.name,
    nodes: wf.nodes,
    connections: wf.connections,
    settings: {}
  };
  await request(`/workflows/${masterId}`, 'PUT', payload);
  console.log('Workflow successfully updated!');
}

fixWorkflow().catch(console.error);
