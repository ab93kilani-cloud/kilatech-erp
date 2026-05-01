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

async function fixDesigner() {
  console.log('Fetching workflow...');
  const wf = await request(`/workflows/${masterId}`, 'GET');
  
  const designer = wf.nodes.find(n => n.name === '🎨 5. Le Designer');
  if (!designer) {
    throw new Error('Node "🎨 5. Le Designer" not found!');
  }

  // Restore the missing parameters using 'gpt-4' to avoid validation errors in UI
  designer.parameters = {
    "resource": "chat",
    "operation": "message",
    "model": "gpt-4",
    "messages": {
      "values": [
        {
          "role": "system",
          "content": "=Tu es le Designer visuel de Kilatech.ma. MISSION : Optimiser l'apparence de l'article et surtout de l'IMAGE DU PRODUIT."
        },
        {
          "role": "user",
          "content": "=DONNÉES : {{ $json.text }}\nNOM PRODUIT : {{ $('PRODUIT').first().json.name }}\nURL IMAGE : {{ $(\"PRODUIT\").first().json.images[0]?.url || \"https://api.kilatech.ma/storage/placeholder.jpg\" }}\n\nCONSIGNES :\n1. Crée une balise ALT SEO parfaite pour le produit : {{ $('PRODUIT').first().json.name }}.\n2. Définis le style CSS Premium.\n3. Instructions de mise en page.\n\nRéponds UNIQUEMENT en JSON : {\"image_alt\": \"...\", \"css_style\": \"...\", \"layout_instructions\": \"...\"}"
        }
      ]
    },
    "options": {
      "maxTokens": 2000
    },
    "requestOptions": {}
  };

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

fixDesigner().catch(console.error);
