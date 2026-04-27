import { useState } from 'react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Ban, Search } from 'lucide-react';
import { toast } from 'sonner';
import { posApi } from '@/app/api/pos';
import { salesApi } from '@/app/api/sales';
import Swal from 'sweetalert2';

interface POSVoidModalProps {
  open: boolean;
  onClose: () => void;
}

export default function POSVoidModal({ open, onClose }: POSVoidModalProps) {
  const [saleNumber, setSaleNumber] = useState('');
  const [reason, setReason] = useState('');
  const [loading, setLoading] = useState(false);
  const [saleDetails, setSaleDetails] = useState<any>(null);

  const handleSearchSale = async () => {
    if (!saleNumber.trim()) {
      toast.error('Please enter a sale number');
      return;
    }

    try {
      setLoading(true);
      // Search by sale number
      const sales = await salesApi.getAll();
      const sale = sales.find((s: any) => s.sale_number === saleNumber.trim());

      if (!sale) {
        toast.error('Sale not found');
        setSaleDetails(null);
        return;
      }

      if (sale.status === 'voided') {
        toast.error('This sale has already been voided');
        setSaleDetails(null);
        return;
      }

      setSaleDetails(sale);
      toast.success('Sale found');
    } catch (error: any) {
      toast.error('Failed to search sale');
      setSaleDetails(null);
    } finally {
      setLoading(false);
    }
  };

  const handleVoidSale = async () => {
    if (!saleDetails) return;

    if (!reason.trim()) {
      toast.error('Please provide a reason for voiding');
      return;
    }

    const result = await Swal.fire({
      title: 'Void This Sale?',
      html: `
        <div class="text-left">
          <p class="mb-2"><strong>Sale #:</strong> ${saleDetails.sale_number}</p>
          <p class="mb-2"><strong>Amount:</strong> ₦${parseFloat(saleDetails.total_amount).toLocaleString()}</p>
          <p class="mb-2"><strong>Reason:</strong> ${reason}</p>
          <p class="text-red-600 mt-4"><strong>Warning:</strong> This will restore stock and cannot be undone!</p>
        </div>
      `,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Void Sale',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#dc2626',
    });

    if (!result.isConfirmed) return;

    try {
      setLoading(true);
      await posApi.voidSale(saleDetails.id, { reason });

      toast.success('Sale voided successfully');
      
      // Reset form
      setSaleNumber('');
      setReason('');
      setSaleDetails(null);
      onClose();
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Failed to void sale');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>Void Sale</DialogTitle>
          <DialogDescription>
            Search for a sale and void it (Admin/Master Admin only)
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 mt-4">
          {/* Search Sale */}
          <div>
            <Label htmlFor="sale-number">Sale Number</Label>
            <div className="flex gap-2 mt-2">
              <Input
                id="sale-number"
                value={saleNumber}
                onChange={(e) => setSaleNumber(e.target.value)}
                placeholder="e.g., SALE-20260302-0001"
                className="flex-1"
                onKeyPress={(e) => e.key === 'Enter' && handleSearchSale()}
              />
              <Button onClick={handleSearchSale} disabled={loading}>
                <Search className="h-4 w-4 mr-2" />
                Search
              </Button>
            </div>
          </div>

          {/* Sale Details */}
          {saleDetails && (
            <div className="bg-blue-50 dark:bg-blue-950/30 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
              <h3 className="font-semibold mb-3">Sale Details</h3>
              <div className="grid grid-cols-2 gap-2 text-sm">
                <div>
                  <p className="text-muted-foreground">Sale Number:</p>
                  <p className="font-semibold">{saleDetails.sale_number}</p>
                </div>
                <div>
                  <p className="text-muted-foreground">Date:</p>
                  <p className="font-semibold">{new Date(saleDetails.sale_date).toLocaleDateString()}</p>
                </div>
                <div>
                  <p className="text-muted-foreground">Total Amount:</p>
                  <p className="font-semibold text-lg">₦{parseFloat(saleDetails.total_amount).toLocaleString()}</p>
                </div>
                <div>
                  <p className="text-muted-foreground">Items:</p>
                  <p className="font-semibold">{saleDetails.items?.length || 0}</p>
                </div>
              </div>
            </div>
          )}

          {/* Void Reason */}
          {saleDetails && (
            <div>
              <Label htmlFor="reason">Reason for Voiding *</Label>
              <Textarea
                id="reason"
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder="Explain why this sale is being voided..."
                rows={3}
                className="mt-2"
              />
            </div>
          )}

          {/* Void Button */}
          {saleDetails && (
            <Button
              onClick={handleVoidSale}
              disabled={loading || !reason.trim()}
              className="w-full bg-red-600 hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-600"
              size="lg"
            >
              <Ban className="h-4 w-4 mr-2" />
              {loading ? 'Voiding...' : 'Void Sale'}
            </Button>
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}
