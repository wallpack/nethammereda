# Architecture

## Overview
Nethammereda is a Laravel + Vue platform for corporate meal ordering by weekly cycle.

## Main Components
- Customer frontend (Vue 3 + Vite + Tailwind).
- Backend API (Laravel 13).
- Admin panel (Filament).
- Queue workers for async jobs.
- Supplier export module (CSV/XLSX).
- Telegram Bot/WebApp integration.

## Core Flow
1. Admin prepares menu and weekly cycle.
2. Customer browses catalog and submits order.
3. Backend validates cycle status and order lifecycle transitions.
4. Admin tracks and finalizes delivery.
5. Supplier export is generated for external processing.
6. Delivered items are reflected in personal fridge/history.

## Data Domains
- Users and roles.
- Menu categories and items.
- Order cycles, orders, and order items.
- Supplier export artifacts.
- Fridge items and status history.

## Boundaries
- Customer UI and admin UI are separate entry points.
- Telegram integration is an adapter over backend auth and order endpoints.
- Sensitive runtime config stays in `.env` and is never committed.
