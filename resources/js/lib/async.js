export const withTimeout = (promise, timeoutMs, timeoutMessage = 'request_timeout') => {
    return new Promise((resolve, reject) => {
        const timerId = window.setTimeout(() => {
            reject(new Error(timeoutMessage));
        }, timeoutMs);

        Promise.resolve(promise)
            .then((result) => {
                window.clearTimeout(timerId);
                resolve(result);
            })
            .catch((error) => {
                window.clearTimeout(timerId);
                reject(error);
            });
    });
};
