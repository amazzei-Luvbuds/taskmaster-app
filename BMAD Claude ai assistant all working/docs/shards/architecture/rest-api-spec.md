# REST API Spec

```yaml
openapi: 3.0.0
info:
  title: Jarvis AI Assistant API
  version: 1.0.0
  description: Internal API for Apps Script Web App
servers:
  - url: https://script.google.com/macros/s/{scriptId}/exec
    description: Apps Script Web App endpoint

paths:
  /command:
    post:
      summary: Process user command
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                action: 
                  type: string
                  enum: [processCommand, getStatus, getHistory]
                text:
                  type: string
                  description: User command text
                sessionId:
                  type: string
                audio:
                  type: string
                  format: base64
                  description: Audio data for transcription
      responses:
        200:
          description: Command processed successfully
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                  message:
                    type: string
                  data:
                    type: object
                  actions:
                    type: array
                    items:
                      type: object

  /auth:
    get:
      summary: Authenticate user
      responses:
        200:
          description: Authentication successful
          content:
            text/html:
              schema:
                type: string
```
