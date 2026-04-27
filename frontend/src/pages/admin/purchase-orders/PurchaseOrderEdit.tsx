import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { ArrowLeft, Save } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { purchaseOrderApi } from '@/app/api/purchaseOrders';
import { supplierApi } from '@/app/api/suppliers';
import { toast } from 'sonner';
import { DatePicker } from '@/components/DatePicker';

export default function PurchaseOrderEdit() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [loading, setLoading] = useState(false);
  const [suppliers, setSuppliers] = useState<any[]>([]);
  const [formData, setFormData] = useState({
    supplier_id: '',
    expected_delivery_date: '',
    shipping_cost: '',
    discount_amount: '',
    notes: '',
  });

  useEffect(() => {
    loadPurchaseOrder();
    loadSuppliers();
  }, [id]);

  const loadPurchaseOrder = async () => {
    try {
      const po = await purchaseOrderApi.getById(parseInt(id!));
      setFormData({
        supplier_id: po.supplier_id?.toString() || '',
        expected_delivery_date: po.expected_delivery_date || '',
        shipping_cost: po.shipping_cost?.toString() || '',
        discount_amount: po.discount_amount?.toString() || '',
        notes: po.notes || '',
      });
    } catch (error: any) {
      toast.error('Failed to load purchase order');
      console.error(error);
    }
  };

  const loadSuppliers = async () => {
    try {
      const data = await supplierApi.getAll();
      setSuppliers(data);
    } catch (error) {
      console.error('Failed to load suppliers:', error);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    // Client-side validation
    if (!formData.supplier_id) {
      toast.error('Please select a supplier');
      return;
    }

    try {
      setLoading(true);
      const dataToSend = {
        supplier_id: parseInt(formData.supplier_id),
        expected_delivery_date: formData.expected_delivery_date || undefined,
        shipping_cost: formData.shipping_cost ? parseFloat(formData.shipping_cost) : undefined,
        discount_amount: formData.discount_amount ? parseFloat(formData.discount_amount) : undefined,
        notes: formData.notes || undefined,
      };

      await purchaseOrderApi.update(parseInt(id!), dataToSend);
      toast.success('Purchase order updated successfully');
      navigate(`/admin/purchase-orders/${id}`);
    } catch (error: any) {
      console.error('Update error:', error.response?.data);
      const errorMsg = error.response?.data?.message || 'Failed to update purchase order';
      toast.error(errorMsg);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div className="flex items-center space-x-4">
          <Button variant="ghost" size="icon" onClick={() => navigate(-1)}>
            <ArrowLeft className="h-5 w-5" />
          </Button>
          <div>
            <h1 className="text-3xl font-bold">Edit Purchase Order</h1>
            <p className="text-muted-foreground">Update purchase order details (Draft/Pending only)</p>
          </div>
        </div>
      </div>

      <form onSubmit={handleSubmit}>
        <Card>
          <CardHeader>
            <CardTitle>Purchase Order Information</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label>Supplier *</Label>
                <Select
                  value={formData.supplier_id}
                  onValueChange={(value) => setFormData({ ...formData, supplier_id: value })}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Select supplier" />
                  </SelectTrigger>
                  <SelectContent>
                    {suppliers.map((supplier) => (
                      <SelectItem key={supplier.id} value={supplier.id.toString()}>
                        {supplier.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-2">
                <Label>Expected Delivery Date</Label>
                <DatePicker
                  value={formData.expected_delivery_date}
                  onChange={(v) => setFormData({ ...formData, expected_delivery_date: v })}
                  placeholder="Select delivery date"
                />
              </div>

              <div className="space-y-2">
                <Label>Shipping Cost (₦)</Label>
                <Input
                  type="number"
                  min="0"
                  step="0.01"
                  value={formData.shipping_cost}
                  onChange={(e) => setFormData({ ...formData, shipping_cost: e.target.value })}
                  placeholder="0.00"
                />
              </div>

              <div className="space-y-2">
                <Label>Discount Amount (₦)</Label>
                <Input
                  type="number"
                  min="0"
                  step="0.01"
                  value={formData.discount_amount}
                  onChange={(e) => setFormData({ ...formData, discount_amount: e.target.value })}
                  placeholder="0.00"
                />
              </div>
            </div>

            <div className="space-y-2">
              <Label>Notes</Label>
              <Textarea
                value={formData.notes}
                onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                rows={3}
                placeholder="Additional notes or instructions"
              />
            </div>

            <div className="flex justify-end space-x-2 pt-4">
              <Button type="button" variant="outline" onClick={() => navigate(-1)}>
                Cancel
              </Button>
              <Button type="submit" disabled={loading}>
                <Save className="mr-2 h-4 w-4" />
                {loading ? 'Updating...' : 'Update Purchase Order'}
              </Button>
            </div>
          </CardContent>
        </Card>
      </form>
    </div>
  );
}
