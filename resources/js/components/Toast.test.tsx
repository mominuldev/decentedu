import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { Toast } from './Toast';

describe('Toast Component', () => {
    it('renders nothing when toast is null', () => {
        const { container } = render(<Toast toast={null} />);
        expect(container.firstChild).toBeNull();
    });

    it('renders success toast message', () => {
        render(<Toast toast={{ tone: 'success', message: 'Report generated successfully.' }} />);
        expect(screen.getByRole('status')).toHaveClass('bg-emerald-600');
        expect(screen.getByText('Report generated successfully.')).toBeInTheDocument();
    });

    it('renders warning toast message', () => {
        render(<Toast toast={{ tone: 'warning', message: 'No data found for selected parameters.' }} />);
        expect(screen.getByRole('status')).toHaveClass('bg-amber-500');
        expect(screen.getByText('No data found for selected parameters.')).toBeInTheDocument();
    });

    it('renders error toast message', () => {
        render(<Toast toast={{ tone: 'error', message: 'Validation failed.' }} />);
        expect(screen.getByRole('status')).toHaveClass('bg-rose-600');
        expect(screen.getByText('Validation failed.')).toBeInTheDocument();
    });

    it('calls onClose when close button is clicked', () => {
        const onClose = vi.fn();
        render(<Toast toast={{ tone: 'error', message: 'Error' }} onClose={onClose} />);
        fireEvent.click(screen.getByRole('button', { name: 'Close notification' }));
        expect(onClose).toHaveBeenCalledOnce();
    });
});
