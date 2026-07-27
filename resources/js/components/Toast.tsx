import { useEffect } from 'react';
import { CheckCircle2, AlertTriangle, XCircle, X } from 'lucide-react';

export type ToastTone = 'success' | 'warning' | 'error';

export interface ToastState {
    tone: ToastTone;
    message: string;
}

interface ToastProps {
    toast: ToastState | null;
    onClose?: () => void;
    autoCloseDuration?: number;
}

export function Toast({ toast, onClose, autoCloseDuration = 5000 }: ToastProps) {
    useEffect(() => {
        if (!toast || !onClose) return;
        const timer = setTimeout(() => {
            onClose();
        }, autoCloseDuration);
        return () => clearTimeout(timer);
    }, [toast, onClose, autoCloseDuration]);

    if (!toast) return null;

    const toneClasses = {
        success: 'bg-emerald-600 text-white shadow-emerald-900/20',
        warning: 'bg-amber-500 text-white shadow-amber-900/20',
        error: 'bg-rose-600 text-white shadow-rose-900/20',
    }[toast.tone];

    const Icon = {
        success: CheckCircle2,
        warning: AlertTriangle,
        error: XCircle,
    }[toast.tone];

    return (
        <div
            role="status"
            aria-live="polite"
            className={`fixed right-5 top-5 z-50 flex items-center gap-2.5 rounded-xl px-4 py-3 text-[13.5px] font-medium shadow-xl transition-all duration-200 ${toneClasses}`}
        >
            <Icon size={18} className="shrink-0" />
            <span className="flex-1">{toast.message}</span>
            {onClose && (
                <button
                    type="button"
                    onClick={onClose}
                    className="ml-1.5 rounded-lg p-1 text-white/80 transition-colors hover:bg-white/20 hover:text-white"
                    aria-label="Close notification"
                >
                    <X size={14} />
                </button>
            )}
        </div>
    );
}

// Toast Context & Provider for global use
import { createContext, useContext, useState, useCallback } from 'react';

interface ToastContextType {
    showToast: (message: string, tone?: ToastTone) => void;
    success: (message: string) => void;
    error: (message: string) => void;
    warning: (message: string) => void;
}

const ToastContext = createContext<ToastContextType | undefined>(undefined);

let globalToastId = 0;

export function ToastProvider({ children }: { children: React.ReactNode }) {
    const [toasts, setToasts] = useState<ToastState[]>([]);

    const removeToast = useCallback((id: string) => {
        setToasts((prev) => prev.filter((t) => t.id !== id));
    }, []);

    const showToast = useCallback((message: string, tone: ToastTone = 'success') => {
        const id = `toast-${++globalToastId}`;
        setToasts((prev) => [...prev, { id, tone, message }]);
    }, []);

    const success = useCallback((message: string) => showToast(message, 'success'), [showToast]);
    const error = useCallback((message: string) => showToast(message, 'error'), [showToast]);
    const warning = useCallback((message: string) => showToast(message, 'warning'), [showToast]);

    return (
        <ToastContext.Provider value={{ showToast, success, error, warning }}>
            {children}
            <div className="fixed right-5 top-5 z-[9999] flex flex-col gap-2 pointer-events-none max-w-sm w-full">
                {toasts.map((t) => (
                    <div key={t.id} className="pointer-events-auto">
                        <SingleToast toast={t} onClose={() => removeToast(t.id!)} />
                    </div>
                ))}
            </div>
        </ToastContext.Provider>
    );
}

function SingleToast({ toast, onClose, autoCloseDuration = 4000 }: { toast: ToastState; onClose: () => void; autoCloseDuration?: number }) {
    useEffect(() => {
        const timer = setTimeout(() => {
            onClose();
        }, autoCloseDuration);
        return () => clearTimeout(timer);
    }, [onClose, autoCloseDuration]);

    const toneClasses = {
        success: 'bg-emerald-600 text-white shadow-emerald-900/20',
        warning: 'bg-amber-500 text-white shadow-amber-900/20',
        error: 'bg-rose-600 text-white shadow-rose-900/20',
    }[toast.tone];

    const Icon = {
        success: CheckCircle2,
        warning: AlertTriangle,
        error: XCircle,
    }[toast.tone];

    return (
        <div
            role="status"
            aria-live="polite"
            className={`flex items-center gap-2.5 rounded-xl px-4 py-3 text-[13.5px] font-medium shadow-xl transition-all duration-200 ${toneClasses}`}
        >
            <Icon size={18} className="shrink-0" />
            <span className="flex-1">{toast.message}</span>
            <button
                type="button"
                onClick={onClose}
                className="ml-1.5 rounded-lg p-1 text-white/80 transition-colors hover:bg-white/20 hover:text-white"
                aria-label="Close notification"
            >
                <X size={14} />
            </button>
        </div>
    );
}

export function useToast() {
    const context = useContext(ToastContext);
    if (!context) {
        return {
            showToast: () => {},
            success: () => {},
            error: () => {},
            warning: () => {},
        };
    }
    return context;
}

