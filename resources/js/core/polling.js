export function createPoller(fetchData, {
    intervalMs = 4000,
    maxIntervalMs = 30000,
    onData,
    onError,
    runImmediately = true,
} = {}) {
    let stopped = false;
    let inFlight = false;
    let timer = null;
    let currentInterval = intervalMs;

    const schedule = () => {
        if (!stopped) timer = window.setTimeout(tick, currentInterval);
    };

    const tick = async () => {
        if (stopped) return;
        if (document.hidden) {
            schedule();
            return;
        }
        if (inFlight) return;

        inFlight = true;
        try {
            const data = await fetchData();
            currentInterval = intervalMs;
            onData?.(data);
        } catch (error) {
            currentInterval = Math.min(currentInterval * 2, maxIntervalMs);
            onError?.(error);
        } finally {
            inFlight = false;
            schedule();
        }
    };

    const onVisibilityChange = () => {
        if (!document.hidden && !inFlight) tick();
    };

    document.addEventListener('visibilitychange', onVisibilityChange);
    if (runImmediately) tick();
    else schedule();

    return {
        stop() {
            stopped = true;
            window.clearTimeout(timer);
            document.removeEventListener('visibilitychange', onVisibilityChange);
        },
        async refresh() {
            if (!stopped && !inFlight) await tick();
        },
    };
}
