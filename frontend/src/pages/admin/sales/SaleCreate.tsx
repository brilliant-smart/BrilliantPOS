import { useState, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { ArrowLeft, Plus, Trash2, Scan } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { salesApi } from '@/app/api/sales';
import { productApi } from '@/app/api/products';
import { toast } from 'sonner';
import BarcodeScanner from '@/components/BarcodeScanner';
import { DatePicker } from '@/components/DatePicker';
import { createBarcodeScanner } from '@/utils/barcodeScanner';

export default function SaleCreate() {
  const navigate = useNavigate();
  const [loading, setLoading] = useState(false);
  const [products, setProducts] = useState<any[]>([]);
  const [formData, setFormData] = useState({
    sale_date: new Date().toISOString().split('T')[0],
    customer_name: '',
    customer_phone: '',
    sale_type: 'cash' as 'cash' | 'credit' | 'online' | 'pos',
    payment_status: 'paid' as 'unpaid' | 'partially_paid' | 'paid',
    notes: '',
  });
  const [items, setItems] = useState<any[]>([
    { product_id: '', quantity: 1, unit_price: 0, unit_type: 'piece' },
  ]);
  const [showScanner, setShowScanner] = useState(false);
  const [scanningForIndex, setScanningForIndex] = useState<number | null>(null);
  const externalScannerRef = useRef<any>(null);

  useEffect(() => {
    loadProducts();
  }, []);

  // External barcode scanner support - AUTO-DETECT enabled by default
  useEffect(() => {
    if (!externalScannerRef.current) {
      externalScannerRef.current = createBarcodeScanner({
        onScan: async (barcode) => {
          try {
            const product = await productApi.searchByBarcode(barcode);
            
            // Find first empty row or add new row
            const emptyIndex = items.findIndex(item => !item.product_id);
            if (emptyIndex !== -1) {
              // Update the items state properly
              const newItems = [...items];
              newItems[emptyIndex] = {
                ...newItems[emptyIndex],
                product_id: product.id.toString(),
                unit_price: product.price || 0,
                unit_type: product.unit_type || 'piece'
              };
              setItems(newItems);
            } else {
              // Add new item
              setItems([...items, {
                product_id: product.id.toString(),
                quantity: 1,
                unit_price: product.price || 0,
                unit_type: product.unit_type || 'piece'
              }]);
            }
            
            toast.success(`Added: ${product.name}`);
          } catch (error: any) {
            toast.error(error.response?.data?.message || 'Product not found');
          }
        },
        minLength: 3,
        maxLength: 50,
        preventDefault: true,
        ignoreIfFocusOn: ['input[type="text"]', 'input[type="number"]', 'textarea', 'select'],
      });
      
      externalScannerRef.current.start();
    }

    return () => {
      if (externalScannerRef.current) {
        externalScannerRef.current.stop();
      }
    };
  }, [items, products]);

  const loadProducts = async () => {
    try {
      const data = await productApi.getAll();
      const allProducts = data.data || data;
      
      setProducts(allProducts);
    } catch (error) {
      toast.error('Failed to load products');
    }
  };

  const addItem = () => {
    setItems([...items, { product_id: '', quantity: 1, unit_price: 0, unit_type: 'piece' }]);
  };

  const removeItem = (index: number) => {
    setItems(items.filter((_, i) => i !== index));
  };

  const updateItem = (index: number, field: string, value: any) => {
    const updated = [...items];
    updated[index] = { ...updated[index], [field]: value };
    
    // Auto-fill price when product is selected
    if (field === 'product_id' && value) {
      const product = products.find(p => p.id.toString() === value);
      if (product) {
        updated[index].unit_price = parseFloat(product.price);
      }
    }
    
    setItems(updated);
  };

  const handleBarcodeClick = (index: number) => {
    setScanningForIndex(index);
    setShowScanner(true);
  };

  const handleBarcodeScan = async (barcode: string) => {
    try {
      const product = await productApi.searchByBarcode(barcode);
      
      if (scanningForIndex !== null) {
        updateItem(scanningForIndex, 'product_id', product.id.toString());
        
        // Auto-fill price with selling price
        if (product.price) {
          updateItem(scanningForIndex, 'unit_price', product.price);
        }
        
        toast.success(`Product "${product.name}" added`);
      }
      
      setShowScanner(false);
      setScanningForIndex(null);
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Product not found');
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (items.length === 0 || items.some((i) => !i.product_id || i.quantity <= 0)) {
      toast.error('Please add at least one valid item');
      return;
    }

    try {
      setLoading(true);
      await salesApi.create({
        ...formData,
        items,
      });
      toast.success('Sale recorded successfully');
      navigate('/admin/sales');
    } catch (error: any) {
      console.error('Sale creation error:', error.response?.data);
      const errorMsg = error.response?.data?.message || error.message || 'Failed to record sale';
      const validationErrors = error.response?.data?.errors;
      
      if (validationErrors) {
        // Show first validation error
        const firstError = Object.values(validationErrors)[0];
        toast.error(Array.isArray(firstError) ? firstError[0] : firstError);
      } else {
        toast.error(errorMsg);
      }
    } finally {
      setLoading(false);
    }
  };

  const total = items.reduce((sum, item) => sum + (item.quantity * item.unit_price), 0);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div className="flex items-center space-x-4">
          <Button variant="ghost" size="icon" onClick={() => navigate('/admin/sales')}>
            <ArrowLeft className="h-4 w-4" />
          </Button>
          <div>
            <h1 className="text-3xl font-bold">Record Sale</h1>
            <p className="text-muted-foreground">Create a new sales transaction</p>
          </div>
        </div>
        
        <div className="flex items-center gap-2 bg-green-50 dark:bg-green-950/30 px-3 py-2 rounded-md border border-green-200 dark:border-green-800">
          <div className="h-2 w-2 bg-green-500 rounded-full animate-pulse"></div>
          <Label className="text-sm font-medium text-green-700 dark:text-green-400">
            External Scanner Active (Auto-Detect)
          </Label>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle>Sale Details</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label>Sale Date *</Label>
                <DatePicker
                  value={formData.sale_date}
                  onChange={(v) => setFormData({ ...formData, sale_date: v })}
                  required
                />
              </div>

              <div className="space-y-2">
                <Label>Sale Type *</Label>
                <Select value={formData.sale_type} onValueChange={(v: any) => setFormData({ ...formData, sale_type: v, payment_status: v === 'credit' ? 'unpaid' : 'paid' })}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="cash">Cash</SelectItem>
                    <SelectItem value="pos">POS/Card</SelectItem>
                    <SelectItem value="online">Online Transfer</SelectItem>
                    <SelectItem value="credit">Credit (Pay Later)</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-2">
                <Label>Customer Name</Label>
                <Input
                  placeholder="Optional"
                  value={formData.customer_name}
                  onChange={(e) => setFormData({ ...formData, customer_name: e.target.value })}
                />
              </div>

              <div className="space-y-2">
                <Label>Customer Phone</Label>
                <Input
                  placeholder="Optional"
                  value={formData.customer_phone}
                  onChange={(e) => setFormData({ ...formData, customer_phone: e.target.value })}
                />
              </div>
            </div>

            <div className="space-y-2">
              <Label>Notes</Label>
              <Textarea
                value={formData.notes}
                onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                rows={2}
              />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle>Items</CardTitle>
              <Button type="button" variant="outline" size="sm" onClick={addItem}>
                <Plus className="mr-2 h-4 w-4" />
                Add Item
              </Button>
            </div>
          </CardHeader>
          <CardContent className="space-y-4">
            {items.map((item, index) => (
              <div key={index} className="flex items-end gap-4">
                <div className="flex-1 space-y-2">
                  <Label>Product</Label>
                  <div className="flex gap-2">
                    <Select value={item.product_id} onValueChange={(v) => updateItem(index, 'product_id', v)}>
                      <SelectTrigger>
                        <SelectValue placeholder="Select product" />
                      </SelectTrigger>
                      <SelectContent>
                        {products.map((p) => (
                          <SelectItem key={p.id} value={p.id.toString()}>
                            {p.name} (Stock: {p.stock_quantity})
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <Button
                      type="button"
                      variant="outline"
                      size="icon"
                      onClick={() => handleBarcodeClick(index)}
                      title="Scan barcode"
                    >
                      <Scan className="h-4 w-4" />
                    </Button>
                  </div>
                </div>

                <div className="w-24 space-y-2">
                  <Label>Quantity</Label>
                  <Input
                    type="number"
                    min="1"
                    value={item.quantity || ''}
                    onChange={(e) => updateItem(index, 'quantity', parseInt(e.target.value) || 0)}
                  />
                </div>

                <div className="w-28 space-y-2">
                  <Label>Unit Type</Label>
                  <Select value={item.unit_type || 'piece'} onValueChange={(v) => updateItem(index, 'unit_type', v)}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="piece">Piece</SelectItem>
                      <SelectItem value="carton">Carton</SelectItem>
                      <SelectItem value="box">Box</SelectItem>
                      <SelectItem value="pack">Pack</SelectItem>
                      <SelectItem value="dozen">Dozen</SelectItem>
                      <SelectItem value="kg">Kg</SelectItem>
                      <SelectItem value="liter">Liter</SelectItem>
                      <SelectItem value="meter">Meter</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div className="w-32 space-y-2">
                  <Label>Unit Price (₦)</Label>
                  <Input
                    type="number"
                    min="0"
                    step="0.01"
                    value={item.unit_price || ''}
                    onChange={(e) => updateItem(index, 'unit_price', parseFloat(e.target.value) || 0)}
                  />
                </div>

                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  onClick={() => removeItem(index)}
                  disabled={items.length === 1}
                >
                  <Trash2 className="h-4 w-4" />
                </Button>
              </div>
            ))}

            <div className="pt-4 border-t">
              <div className="flex justify-between text-lg font-semibold">
                <span>Total:</span>
                <span>₦{total.toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
              </div>
            </div>
          </CardContent>
        </Card>

        <div className="flex justify-end space-x-2">
          <Button type="button" variant="outline" onClick={() => navigate('/admin/sales')} disabled={loading}>
            Cancel
          </Button>
          <Button type="submit" disabled={loading}>
            {loading ? 'Recording...' : 'Record Sale'}
          </Button>
        </div>
      </form>

      {/* Barcode Scanner Dialog */}
      <Dialog open={showScanner} onOpenChange={setShowScanner}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>Scan Product Barcode</DialogTitle>
          </DialogHeader>
          <BarcodeScanner
            onScan={handleBarcodeScan}
            onClose={() => {
              setShowScanner(false);
              setScanningForIndex(null);
            }}
            title="Scan to add product"
          />
        </DialogContent>
      </Dialog>
    </div>
  );
}
