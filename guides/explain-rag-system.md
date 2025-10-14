# Your Congressional RAG Chatbot System

## Current Architecture

```
User Question: "What healthcare bills were introduced recently?"
       ↓
1. RETRIEVAL PHASE
   ├── Generate embedding for question
   ├── Search similar embeddings in database
   ├── Find relevant bills/members/actions
   └── Rank by similarity score
       ↓
2. AUGMENTATION PHASE
   ├── Extract relevant data from database
   ├── Format context for AI
   ├── Include bill details, sponsors, dates
   └── Create structured prompt
       ↓
3. GENERATION PHASE
   ├── Send context + question to Claude
   ├── Claude generates informed response
   ├── Response includes specific bill references
   └── Return formatted answer with sources
```

## Key Components

### 1. SemanticSearchService
- Converts questions to embeddings
- Searches for similar content
- Returns ranked results

### 2. CongressChatbotService
- Orchestrates the RAG pipeline
- Combines retrieval with generation
- Formats responses with sources

### 3. DocumentEmbeddingService
- Creates embeddings for all content
- Stores in vector database (embeddings table)
- Enables semantic search

## Why It's Not Working Yet

❌ **Missing embeddings** - The knowledge base isn't vectorized
❌ **No semantic search** - Can't find relevant content
❌ **Generic responses** - AI has no specific data to reference

## After Generating Embeddings

✅ **Semantic retrieval** - Finds relevant bills by meaning
✅ **Contextual responses** - AI references specific legislation  
✅ **Source attribution** - Shows which bills/members mentioned
✅ **Accurate data** - Based on your actual database