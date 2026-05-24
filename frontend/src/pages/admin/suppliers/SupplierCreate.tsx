import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { supplierApi } from '@/app/api/suppliers';
import { toast } from 'sonner';

export default function SupplierCreate() {
  const navigate = useNavigate();
  const [loading, setLoading] = useState(false);
  const [formData, setFormData] = useState({
    code: '',
    name: '',
    contact_person: '',
    email: '',
    phone: '',
    address: '',
    payment_terms: 'credit_30' as 'cash' | 'bank_transfer' | 'cheque' | 'card' | 'credit' | 'credit_7' | 'credit_14' | 'credit_30' | 'credit_60' | 'custom',
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    // Client-side validation
    if (!formData.name.trim()) {
      toast.error('Supplier name is required');
      return;
    }

    if (formData.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
      toast.error('Please enter a valid email address');
      return;
    }

    if (formData.phone && formData.phone.trim().length < 10) {
      toast.error('Please enter a valid phone number (minimum 10 digits)');
      return;
    }

    try {
      setLoading(true);
      // Remove code if empty to let backend auto-generate
      const dataToSend = {
        ...formData,
        name: formData.name.trim(),
        contact_person: formData.contact_person.trim(),
        email: formData.email.trim(),
        phone: formData.phone.trim(),
        address: formData.address.trim(),
      };
      
      if (!dataToSend.code || dataToSend.code.trim() === '') {
        delete dataToSend.code;
      }
      
      await supplierApi.create(dataToSend);
      toast.success('Supplier created successfully');
      navigate('/admin/suppliers');
    } catch (error: any) {
      console.error('Supplier creation error:', error.response?.data);
      const errorMsg = error.response?.data?.message || error.message || 'Failed to create supplier';
      const validationErrors = error.response?.data?.errors;
      
      if (validationErrors) {
        const firstError = Object.values(validationErrors)[0];
        toast.error(Array.isArray(firstError) ? firstError[0] : firstError);
      } else {
        toast.error(errorMsg);
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div className="flex items-center space-x-4">
        <Button variant="ghost" size="icon" onClick={() => navigate('/admin/suppliers')}>
          <ArrowLeft className="h-4 w-4" />
        </Button>
        <div>
          <h1 className="text-3xl font-bold">Create Supplier</h1>
          <p className="text-muted-foreground">Add a new supplier to your system</p>
        </div>
      </div>

      <form onSubmit={handleSubmit}>
        <Card>
          <CardHeader>
            <CardTitle>Supplier Information</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="name">
                  Supplier Name <span className="text-destructive">*</span>
                </Label>
                <Input
                  id="name"
                  placeholder="ABC Suppliers Ltd"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  required
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="code">
                  Supplier Code <span className="text-muted-foreground text-xs">(Auto-generated if empty)</span>
                </Label>
                <Input
                  id="code"
                  placeholder="Leave empty to auto-generate (e.g., SUP-2026-0001)"
                  value={formData.code}
                  onChange={(e) => setFormData({ ...formData, code: e.target.value.toUpperCase() })}
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="contact_person">Contact Person</Label>
                <Input
                  id="contact_person"
                  placeholder="John Doe"
                  value={formData.contact_person}
                  onChange={(e) => setFormData({ ...formData, contact_person: e.target.value })}
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="email">Email</Label>
                <Input
                  id="email"
                  type="email"
                  placeholder="contact@supplier.com"
                  value={formData.email}
                  onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="phone">Phone</Label>
                <Input
                  id="phone"
                  placeholder="+234 XXX XXX XXXX"
                  value={formData.phone}
                  onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="payment_terms">Default Payment Method</Label>
                <Select
                  value={formData.payment_terms}
                  onValueChange={(value: any) => setFormData({ ...formData, payment_terms: value })}
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="cash">💵 Cash Payment</SelectItem>
                    <SelectItem value="bank_transfer">🏦 Bank Transfer</SelectItem>
                    <SelectItem value="cheque">📝 Cheque</SelectItem>
                    <SelectItem value="card">💳 Card Payment</SelectItem>
                    <SelectItem value="credit">📋 Credit (Pay Later - Default 30 days)</SelectItem>
                    <SelectItem value="credit_7">⏰ Credit 7 Days</SelectItem>
                    <SelectItem value="credit_14">⏰ Credit 14 Days</SelectItem>
                    <SelectItem value="credit_30">⏰ Credit 30 Days</SelectItem>
                    <SelectItem value="credit_60">⏰ Credit 60 Days</SelectItem>
                    <SelectItem value="custom">⚙️ Custom Terms</SelectItem>
                  </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">
                  This will be the default payment method when creating POs for this supplier
                </p>
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="address">Address</Label>
              <Textarea
                id="address"
                placeholder="Full address"
                value={formData.address}
                onChange={(e) => setFormData({ ...formData, address: e.target.value })}
                rows={3}
              />
            </div>

            <div className="flex justify-end space-x-2 pt-4">
              <Button
                type="button"
                variant="outline"
                onClick={() => navigate('/admin/suppliers')}
                disabled={loading}
              >
                Cancel
              </Button>
              <Button type="submit" disabled={loading}>
                {loading ? 'Creating...' : 'Create Supplier'}
              </Button>
            </div>
          </CardContent>
        </Card>
      </form>
    </div>
  );
}
