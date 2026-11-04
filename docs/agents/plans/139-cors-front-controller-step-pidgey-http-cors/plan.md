# Plan: CORS front-controller step (`Pidgey\Http\Cors`)

Issue: [139-cors-front-controller-step-pidgey-http-cors.md](../../issues/139-cors-front-controller-step-pidgey-http-cors.md)

## Overview
Add the CORS step of the front-controller pipeline: a new `Pidgey\Http\Cors` class (preflight answer and headers on the final response) wired into `FrontController::handle()`. Backend only.

See [backend.md](backend.md) for the full plan.
