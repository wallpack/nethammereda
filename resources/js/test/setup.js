import { afterEach, vi } from 'vitest';

class ResizeObserverMock {
    observe() {}

    unobserve() {}

    disconnect() {}
}

globalThis.ResizeObserver = ResizeObserverMock;
globalThis.IntersectionObserver = ResizeObserverMock;
globalThis.matchMedia = globalThis.matchMedia ?? (() => ({
    matches: false,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    addListener: vi.fn(),
    removeListener: vi.fn(),
    dispatchEvent: vi.fn(),
}));

afterEach(() => {
    vi.restoreAllMocks();
    document.body.innerHTML = '';
    localStorage.clear();
    sessionStorage.clear();
    delete window.Telegram;
});
