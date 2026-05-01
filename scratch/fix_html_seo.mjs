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
    systemPrompt.content = `=Tu es l'Intégrateur Web de Kilatech.ma.
MISSION : Tu dois générer le code HTML FINAL de l'article.

DONNÉES À TA DISPOSITION :
1. TEXTE FINAL : {{ $node["Dossier de Redaction Final"].json.tisseur }}
2. URL IMAGE : {{ $node["Dossier de Redaction Final"].json.image_url }}
3. CONSEILS DESIGNER : {{ $node["🎨 5. Le Designer"].json.text }}

RÈGLES ABSOLUES DE FORMATAGE :
1. FRAGMENT HTML UNIQUEMENT : INTERDICTION ABSOLUE de générer <!DOCTYPE html>, <html>, <head> ou <body>. Tu dois renvoyer UNIQUEMENT le contenu (les balises h2, p, ul, img...).
2. AUCUN BLOC MARKDOWN. Ne commence pas par \`\`\`html et ne termine pas par \`\`\`. Renvoie UNIQUEMENT le code HTML pur.
3. N'INCLUS PAS les posts sociaux (LinkedIn, Facebook). Ton travail est uniquement sur le contenu de la page web.
4. L'IMAGE DOIT ÊTRE VISIBLE ET OPTIMISÉE SEO : Ajoute EXACTEMENT la balise suivante juste après le premier paragraphe : <img src="{{ $node["Dossier de Redaction Final"].json.image_url }}" alt="[Décris l'image avec précision en utilisant les mots-clés du produit]" title="[Titre SEO de l'image]" loading="lazy" width="800" height="auto" class="img-fluid rounded mb-4 shadow-sm">. NE LA METS PAS en commentaire (<!-- -->).
5. Ne fais aucun commentaire textuel à la fin comme "Note : ...".`;
    
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
