import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { FileUpload } from './FileUpload';

vi.mock('@/features/cms/api', () => ({
    pickerAssets: vi.fn().mockResolvedValue([]),
    getAsset: vi.fn().mockResolvedValue({ id: 1, name: 'avatar.jpg', url: '/avatar.jpg' }),
    uploadAssets: vi.fn().mockResolvedValue([]),
    assetFileUrl: (id: string) => `/media/${id}`,
    inputCls: 'input',
}));

function renderWithClient(ui: React.ReactNode) {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>);
}

describe('FileUpload Component', () => {
    it('renders label and Upload button when value is empty', () => {
        renderWithClient(<FileUpload label="Photo" value={null} onChange={vi.fn()} />);
        expect(screen.getByText('Photo')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /upload/i })).toBeInTheDocument();
    });

    it('renders Replace and Remove buttons when value is provided', () => {
        renderWithClient(<FileUpload label="Photo" value="123" onChange={vi.fn()} />);
        expect(screen.getByRole('button', { name: /replace/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /remove/i })).toBeInTheDocument();
    });

    it('calls onChange with null when Remove button is clicked', () => {
        const onChange = vi.fn();
        renderWithClient(<FileUpload label="Photo" value="123" onChange={onChange} />);
        fireEvent.click(screen.getByRole('button', { name: /remove/i }));
        expect(onChange).toHaveBeenCalledWith(null);
    });

    it('opens MediaPicker modal with Browse Library and Upload Files tabs when Upload is clicked', () => {
        renderWithClient(<FileUpload label="Photo" value={null} onChange={vi.fn()} />);
        fireEvent.click(screen.getByRole('button', { name: /upload/i }));
        expect(screen.getByText('Select Media')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /browse library/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /upload files/i })).toBeInTheDocument();
    });
});
