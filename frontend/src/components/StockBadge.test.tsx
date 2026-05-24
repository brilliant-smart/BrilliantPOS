import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { StockBadge } from './StockBadge';

describe('StockBadge', () => {
  it('renders in stock status', () => {
    render(<StockBadge stockStatus="in_stock" />);
    expect(screen.getByText('In Stock')).toBeInTheDocument();
  });

  it('renders low stock status', () => {
    render(<StockBadge stockStatus="low_stock" />);
    expect(screen.getByText('Low Stock')).toBeInTheDocument();
  });

  it('renders out of stock status', () => {
    render(<StockBadge stockStatus="out_of_stock" />);
    expect(screen.getByText('Out of Stock')).toBeInTheDocument();
  });

  it('shows quantity when showQuantity is true', () => {
    render(<StockBadge stockStatus="in_stock" stockQuantity={42} showQuantity />);
    expect(screen.getByText('In Stock')).toBeInTheDocument();
    expect(screen.getByText('(42)')).toBeInTheDocument();
  });

  it('hides quantity by default', () => {
    render(<StockBadge stockStatus="in_stock" stockQuantity={42} />);
    expect(screen.queryByText('(42)')).not.toBeInTheDocument();
  });

  it('does not show quantity when stockQuantity is undefined', () => {
    render(<StockBadge stockStatus="in_stock" showQuantity />);
    expect(screen.queryByText('(')).not.toBeInTheDocument();
  });

  it('applies custom className', () => {
    const { container } = render(<StockBadge stockStatus="in_stock" className="custom-class" />);
    expect(container.querySelector('.custom-class')).toBeInTheDocument();
  });
});