import { useEffect, useState } from 'react';

/**
 * Hold back a rapidly changing value until it settles.
 *
 * The list filters use this so a request goes out once the typing stops
 * rather than once per keystroke.
 */
export function useDebouncedValue<T>(value: T, delay = 300): T {
    const [debounced, setDebounced] = useState(value);

    useEffect(() => {
        const timeout = window.setTimeout(() => setDebounced(value), delay);

        return () => window.clearTimeout(timeout);
    }, [value, delay]);

    return debounced;
}
