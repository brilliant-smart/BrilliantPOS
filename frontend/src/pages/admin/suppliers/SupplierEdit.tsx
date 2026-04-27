import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { toast } from 'sonner';
import { ArrowLeft, Save } from 'lucide-react';
import { supplierApi } from '@/app/api/suppliers';

interface SupplierForm {
  name: string;
  code: string;
  contact_person: string;
  email: string;
  phone: string;
  phone_alt: string;
  address: string;
  city: string;
  state: string;
  country: string;
  payment_terms: string;
  custom_payment_days: string;
  notes: string;
  is_active: boolean;
}

export default function SupplierEdit() {
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const [loading, setLoading] = useState(false);
  const [fetchingSupplier, setFetchingSupplier] = useState(true);
  const [formData, setFormData] = useState<SupplierForm>({
    name: '',
    code: '',
    contact_person: '',
    email: '',
    phone: '',
    phone_alt: '',
    address: '',
    city: '',
    state: '',
    country: 'Nigeria',
    payment_terms: 'credit_30',
    custom_payment_days: '',
    notes: '',
    is_active: true,
  });

  useEffect(() => {
    loadSupplier();
  }, [id]);

  const loadSupplier = async () => {
    if (!id) return;
    try {
      setFetchingSupplier(true);
      const supplier = await supplierApi.getById(parseInt(id));
      
      setFormData({
        name: supplier.name || '',
        code: supplier.code || '',
        contact_person: supplier.contact_person || '',
        email: supplier.email || '',
        phone: supplier.phone || '',
        phone_alt: supplier.phone_alt || '',
        address: supplier.address || '',
        city: supplier.city || '',
        state: supplier.state || '',
        country: supplier.country || 'Nigeria',
        payment_terms: supplier.payment_terms || 'credit_30',
        custom_payment_days: supplier.custom_payment_days?.toString() || '',
        notes: supplier.notes || '',
        is_active: supplier.is_active ?? true,
      });
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Failed to load supplier');
      navigate('/admin/suppliers');
    } finally {
      setFetchingSupplier(false);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!id) return;

    try {
      setLoading(true);
      const dataToSend = {
        ...formData,
        custom_payment_days: formData.custom_payment_days ? parseInt(formData.custom_payment_days) : null,
      };
      await supplierApi.update(parseInt(id), dataToSend);
      toast.success('Supplier updated successfully!');
      navigate('/admin/suppliers');
    } catch (error: any) {
      console.error('Supplier update error:', error.response?.data);
      const errorMsg = error.response?.data?.message || 'Failed to update supplier';
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

  const handleChange = (field: keyof SupplierForm, value: any) => {
    setFormData(prev => ({ ...prev, [field]: value }));
  };

  if (fetchingSupplier) {
    return (
      <div className="container mx-auto p-6">
        <div className="flex items-center justify-center h-64">
          <div className="text-center">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto"></div>
            <p className="mt-4 text-muted-foreground">Loading supplier...</p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="container mx-auto p-6">
      <div className="mb-6">
        <Button
          variant="ghost"
          onClick={() => navigate('/admin/suppliers')}
          className="mb-4"
        >
          <ArrowLeft className="h-4 w-4 mr-2" />
          Back to Suppliers
        </Button>
        <h1 className="text-3xl font-bold">Edit Supplier</h1>
        <p className="text-muted-foreground mt-2">Update supplier information</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Supplier Information</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {/* Name */}
              <div className="space-y-2">
                <Label htmlFor="name">
                  Supplier Name <span className="text-red-500 dark:text-red-400">*</span>
                </Label>
                <Input
                  id="name"
                  value={formData.name}
                  onChange={(e) => handleChange('name', e.target.value)}
                  placeholder="Enter supplier name"
                  required
                />
              </div>

              {/* Code */}
              <div className="space-y-2">
                <Label htmlFor="code">
                  Supplier Code <span className="text-red-500 dark:text-red-400">*</span>
                </Label>
                <Input
                  id="code"
                  value={formData.code}
                  onChange={(e) => handleChange('code', e.target.value.toUpperCase())}
                  placeholder="SUP001"
                  required
                />
              </div>

              {/* Contact Person */}
              <div className="space-y-2">
                <Label htmlFor="contact_person">Contact Person</Label>
                <Input
                  id="contact_person"
                  value={formData.contact_person}
                  onChange={(e) => handleChange('contact_person', e.target.value)}
                  placeholder="Enter contact person name"
                />
              </div>

              {/* Email */}
              <div className="space-y-2">
                <Label htmlFor="email">Email</Label>
                <Input
                  id="email"
                  type="email"
                  value={formData.email}
                  onChange={(e) => handleChange('email', e.target.value)}
                  placeholder="supplier@example.com"
                />
              </div>

              {/* Phone */}
              <div className="space-y-2">
                <Label htmlFor="phone">Phone</Label>
                <Input
                  id="phone"
                  value={formData.phone}
                  onChange={(e) => handleChange('phone', e.target.value)}
                  placeholder="+234 XXX XXX XXXX"
                />
              </div>

              {/* Alt Phone */}
              <div className="space-y-2">
                <Label htmlFor="phone_alt">Alternative Phone</Label>
                <Input
                  id="phone_alt"
                  value={formData.phone_alt}
                  onChange={(e) => handleChange('phone_alt', e.target.value)}
                  placeholder="+234 XXX XXX XXXX"
                />
              </div>

              {/* City */}
              <div className="space-y-2">
                <Label htmlFor="city">City</Label>
                <Input
                  id="city"
                  value={formData.city}
                  onChange={(e) => handleChange('city', e.target.value)}
                  placeholder="e.g., Lagos"
                />
              </div>

              {/* State */}
              <div className="space-y-2">
                <Label htmlFor="state">State</Label>
                <Input
                  id="state"
                  value={formData.state}
                  onChange={(e) => handleChange('state', e.target.value)}
                  placeholder="e.g., Lagos State"
                />
              </div>

              {/* Country */}
              <div className="space-y-2">
                <Label htmlFor="country">Country</Label>
                <Input
                  id="country"
                  value={formData.country}
                  onChange={(e) => handleChange('country', e.target.value)}
                  placeholder="Nigeria"
                />
              </div>

              {/* Payment Terms */}
              <div className="space-y-2">
                <Label htmlFor="payment_terms">Default Payment Method</Label>
                <Select
                  value={formData.payment_terms}
                  onValueChange={(value) => handleChange('payment_terms', value)}
                >
                  <SelectTrigger id="payment_terms">
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

              {/* Custom Payment Days */}
              {formData.payment_terms === 'custom' && (
                <div className="space-y-2">
                  <Label htmlFor="custom_payment_days">Custom Payment Days</Label>
                  <Input
                    id="custom_payment_days"
                    type="number"
                    min="1"
                    value={formData.custom_payment_days}
                    onChange={(e) => handleChange('custom_payment_days', e.target.value)}
                    placeholder="e.g., 45"
                  />
                </div>
              )}

              {/* Status */}
              <div className="space-y-2">
                <Label htmlFor="is_active">Status</Label>
                <Select
                  value={formData.is_active ? 'active' : 'inactive'}
                  onValueChange={(value) => handleChange('is_active', value === 'active')}
                >
                  <SelectTrigger id="is_active">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="active">Active</SelectItem>
                    <SelectItem value="inactive">Inactive</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            {/* Address */}
            <div className="space-y-2">
              <Label htmlFor="address">Address</Label>
              <Textarea
                id="address"
                value={formData.address}
                onChange={(e) => handleChange('address', e.target.value)}
                placeholder="Enter full address"
                rows={3}
              />
            </div>

            {/* Notes */}
            <div className="space-y-2">
              <Label htmlFor="notes">Notes</Label>
              <Textarea
                id="notes"
                value={formData.notes}
                onChange={(e) => handleChange('notes', e.target.value)}
                placeholder="Additional notes about this supplier"
                rows={3}
              />
            </div>

            {/* Actions */}
            <div className="flex gap-4">
              <Button type="submit" disabled={loading}>
                <Save className="h-4 w-4 mr-2" />
                {loading ? 'Saving...' : 'Save Changes'}
              </Button>
              <Button
                type="button"
                variant="outline"
                onClick={() => navigate('/admin/suppliers')}
                disabled={loading}
              >
                Cancel
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
