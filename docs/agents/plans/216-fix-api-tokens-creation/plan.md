# Plan: Implement the API tokens admin endpoints (list, create, revoke)

Issue: [216-fix-api-tokens-creation.md](../../issues/216-fix-api-tokens-creation.md)

## Overview

Implement the three gated admin endpoints `GET /api-tokens.json`, `POST /api-tokens.json` and `DELETE /api-tokens/:id.json` on top of the existing `api_tokens` table, `ApiToken` model and `ApiTokenRepository`. The domain logic lives in a new `Pidgey\Auth\ApiTokens` class with an `ApiTokenValue` secret helper, validation in `Pidgey\Http\ApiTokenRules`, serialization in `Pidgey\Http\ApiTokenPayload`. Backend only: the frontend already matches the contract.

See [backend.md](backend.md) for the full plan.
