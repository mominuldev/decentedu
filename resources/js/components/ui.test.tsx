import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { Button, Badge } from './ui';

describe('Button', () => {
  it('renders children and responds to clicks', () => {
    const onClick = vi.fn();
    render(<Button onClick={onClick}>Save</Button>);

    fireEvent.click(screen.getByRole('button', { name: 'Save' }));
    expect(onClick).toHaveBeenCalledOnce();
  });

  it('applies smaller padding classes for size="sm"', () => {
    render(<Button size="sm">Compact</Button>);
    expect(screen.getByRole('button', { name: 'Compact' })).toHaveClass('px-2.5', 'py-1.5', 'text-xs');
  });

  it('defaults to the medium size and primary variant', () => {
    render(<Button>Default</Button>);
    const btn = screen.getByRole('button', { name: 'Default' });
    expect(btn).toHaveClass('px-3.5', 'py-2', 'text-sm');
    expect(btn).toHaveClass('bg-brand-600');
  });

  it('disables the button and shows disabled styling', () => {
    render(<Button disabled>Disabled</Button>);
    expect(screen.getByRole('button', { name: 'Disabled' })).toBeDisabled();
  });
});

describe('Badge', () => {
  it('renders its children with the requested tone', () => {
    render(<Badge tone="success">Active</Badge>);
    expect(screen.getByText('Active')).toBeInTheDocument();
  });

  it('applies compact classes for size="sm"', () => {
    render(<Badge size="sm">Small</Badge>);
    expect(screen.getByText('Small')).toHaveClass('text-[11px]');
  });
});
