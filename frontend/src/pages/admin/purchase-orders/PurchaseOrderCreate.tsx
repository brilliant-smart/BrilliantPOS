import { useState, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { ArrowLeft, Plus, Trash2, Scan, TrendingUp, Calendar, Package } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { purchaseOrderApi } from '@/app/api/purchaseOrders';
import { supplierApi } from '@/app/api/suppliers';
import { productApi } from '@/app/api/products';
import { priceHistoryApi } from '@/app/api/priceHistory';
import { Supplier } from '@/types/ims';
import { toast } from 'sonner';
import BarcodeScanner from '@/components/BarcodeScanner';
import { MonthYearPicker } from '@/components/MonthYearPicker';
import { DatePicker } from '@/components/DatePicker';
import PriceComparisonModal from '@/components/PriceComparisonModal';
import { createBarcodeScanner } from '@/utils/barcodeScanner';
import { ProductSearchSelect } from '@/components/ProductSearchSelect';

export default function PurchaseOrderCreate() {
  const navigate = useNavigate();
  const [loading, setLoading] = useState(false);
  const [suppliers, setSuppliers] = useState<Supplier[]>([]);
  const [products, setProducts] = useState<any[]>([]);
  const [priceComparisonOpen, setPriceComparisonOpen] = useState(false);
  const [priceComparisons, setPriceComparisons] = useState<any[]>([]);
  const [loadingComparison, setLoadingComparison] = useState(false);
  const [formData, setFormData] = useState({
    supplier_id: '',
    order_date: new Date().toISOString().split('T')[0],
    expected_delivery_date: '',
    payment_method: 'credit' as 'cash' | 'bank_transfer' | 'cheque' | 'card' | 'credit',
    payment_due_date: '',
    notes: '',
  });
  const [items, setItems] = useState<any[]>([
    { 
      product_id: '', 
      quantity_ordered: 1, 
      unit_cost: 0, 
      unit_type: 'piece',
      batch_number: '',
      expiry_date: '',
      manufacturing_date: ''
    },
  ]);
  const [showScanner, setShowScanner] = useState(false);
  const [scanningForIndex, setScanningForIndex] = useState<number | null>(null);
  const externalScannerRef = useRef<any>(null);

  useEffect(() => {
    loadSuppliers();
    loadProducts();
  }, []);

  // External barcode scanner support for PO - AUTO-DETECT enabled by default
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
                unit_cost: product.cost_price || 0,
                unit_type: product.unit_type || 'piece',
              };
              setItems(newItems);
            } else {
              // Add new item
              setItems([...items, {
                product_id: product.id.toString(),
                quantity_ordered: 1,
                unit_cost: product.cost_price || 0,
                unit_type: product.unit_type || 'piece',
                manufacturing_date: '',
                expiry_date: '',
                batch_number: '',
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

  const loadSuppliers = async () => {
    try {
      const data = await supplierApi.getAll();
      setSuppliers(data.filter((s: Supplier) => s.is_active));
    } catch (error) {
      toast.error('Failed to load suppliers');
    }
  };

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
    setItems([...items, { 
      product_id: '', 
      quantity_ordered: 1, 
      unit_cost: 0, 
      unit_type: 'piece',
      batch_number: '',
      expiry_date: '',
      manufacturing_date: ''
    }]);
  };

  const handlePriceComparison = async () => {
    const productIds = items
      .filter(item => item.product_id)
      .map(item => parseInt(item.product_id));

    if (productIds.length === 0) {
      toast.error('Please add products first');
      return;
    }

    setLoadingComparison(true);
    try {
      const response = await priceHistoryApi.getPriceComparison(productIds);
      setPriceComparisons(response.price_comparison || []);
      setPriceComparisonOpen(true);
    } catch (error: any) {
      console.error('Price comparison error:', error);
      toast.error(error.response?.data?.message || 'Failed to load price comparison');
    } finally {
      setLoadingComparison(false);
    }
  };

  const getCurrentPrices = () => {
    const prices: { [key: number]: number } = {};
    items.forEach(item => {
      if (item.product_id && item.unit_cost) {
        prices[parseInt(item.product_id)] = parseFloat(item.unit_cost);
      }
    });
    return prices;
  };

  const removeItem = (index: number) => {
    setItems(items.filter((_, i) => i !== index));
  };

  const updateItem = (index: number, field: string, value: any) => {
    const updated = [...items];
    updated[index] = { ...updated[index], [field]: value };
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
        
        // Auto-fill unit cost with product's cost price if available
        if (product.cost_price) {
          updateItem(scanningForIndex, 'unit_cost', product.cost_price);
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

    if (!formData.supplier_id) {
      toast.error('Please select a supplier');
      return;
    }

    if (items.length === 0 || items.some((i) => !i.product_id || i.quantity_ordered <= 0)) {
      toast.error('Please add at least one valid item');
      return;
    }

    // Validate unit cost for all items
    if (items.some((i) => !i.unit_cost || i.unit_cost <= 0)) {
      toast.error('Please enter unit cost for all items');
      return;
    }

    for (let i = 0; i < items.length; i++) {
      const item = items[i];
      const product = products.find(p => p.id.toString() === item.product_id);
      
      if (product) {
        // Check if batch number is required
        if (product.track_batch && !item.batch_number?.trim()) {
          toast.error(`Batch number is required for "${product.name}" (Item ${i + 1})`);
          return;
        }
        
        // Expiry date is optional even if track_expiry is enabled
        // No validation needed here - expiry tracking is for notification purposes only
        
        // Validate expiry date is in the future if provided
        if (item.expiry_date) {
          const expiryDate = new Date(item.expiry_date);
          const today = new Date();
          today.setHours(0, 0, 0, 0);
          
          if (expiryDate < today) {
            toast.error(`Expiry date for "${product.name}" (Item ${i + 1}) cannot be in the past`);
            return;
          }
        }
        
        // Validate manufacturing date is not in the future if provided
        if (item.manufacturing_date) {
          const mfgDate = new Date(item.manufacturing_date);
          const today = new Date();
          today.setHours(23, 59, 59, 999);
          
          if (mfgDate > today) {
            toast.error(`Manufacturing date for "${product.name}" (Item ${i + 1}) cannot be in the future`);
            return;
          }
        }
        
        // Validate manufacturing date is before expiry date
        if (item.manufacturing_date && item.expiry_date) {
          const mfgDate = new Date(item.manufacturing_date);
          const expDate = new Date(item.expiry_date);
          
          if (mfgDate >= expDate) {
            toast.error(`Manufacturing date must be before expiry date for "${product.name}" (Item ${i + 1})`);
            return;
          }
        }
      }
    }

    try {
      setLoading(true);
      await purchaseOrderApi.create({
        ...formData,
        supplier_id: parseInt(formData.supplier_id),
        items,
      });
      toast.success('Purchase order created successfully');
      navigate('/admin/purchase-orders');
    } catch (error: any) {
      console.error('Purchase order creation error:', error.response?.data);
      const errorMsg = error.response?.data?.message || error.message || 'Failed to create purchase order';
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

  const total = items.reduce((sum, item) => sum + (item.quantity_ordered * item.unit_cost), 0);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div className="flex items-center space-x-4">
          <Button variant="ghost" size="icon" onClick={() => navigate('/admin/purchase-orders')}>
            <ArrowLeft className="h-4 w-4" />
          </Button>
          <div>
            <h1 className="text-3xl font-bold">Create Purchase Order</h1>
            <p className="text-muted-foreground">Order stock from suppliers</p>
          </div>
        </div>
        
        <div className="flex items-center gap-2 bg-green-50 dark:bg-green-950/30 px-3 py-2 rounded-md border border-green-200">
          <div className="h-2 w-2 bg-green-500 dark:bg-green-600 rounded-full animate-pulse"></div>
          <Label className="text-sm font-medium text-green-700 dark:text-green-400">
            External Scanner Active (Auto-Detect)
          </Label>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle>Order Details</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label>Supplier *</Label>
                <Select 
                  value={formData.supplier_id} 
                  onValueChange={(v) => {
                    const selectedSupplier = suppliers.find(s => s.id.toString() === v);
                    const defaultPaymentMethod = selectedSupplier?.payment_terms || 'credit';
                    
                    setFormData({ 
                      ...formData, 
                      supplier_id: v,
                      payment_method: defaultPaymentMethod as any
                    });
                  }}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Select supplier" />
                  </SelectTrigger>
                  <SelectContent>
                    {suppliers.map((s) => (
                      <SelectItem key={s.id} value={s.id.toString()}>
                        {s.name} 
                        <span className="text-xs text-muted-foreground ml-2">
                          ({s.payment_terms?.replace('_', ' ') || 'credit'})
                        </span>
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {formData.supplier_id && (
                  <p className="text-xs text-muted-foreground">
                    The payment method is prefilled from the supplier's default payment method
                  </p>
                )}
              </div>

              <div className="space-y-2">
                <Label>Order Date *</Label>
                <DatePicker
                  value={formData.order_date}
                  onChange={(v) => setFormData({ ...formData, order_date: v })}
                  required
                />
              </div>

              <div className="space-y-2">
                <Label>Expected Delivery</Label>
                <DatePicker
                  value={formData.expected_delivery_date}
                  onChange={(v) => setFormData({ ...formData, expected_delivery_date: v })}
                  placeholder="Select delivery date"
                />
              </div>

              <div className="space-y-2">
                <Label>Payment Method *</Label>
                <Select value={formData.payment_method} onValueChange={(v: any) => setFormData({ ...formData, payment_method: v })}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="cash">💵 Cash</SelectItem>
                    <SelectItem value="bank_transfer">🏦 Bank Transfer</SelectItem>
                    <SelectItem value="cheque">📝 Cheque</SelectItem>
                    <SelectItem value="card">💳 Card</SelectItem>
                    <SelectItem value="credit">📋 Credit (Pay Later)</SelectItem>
                    <SelectItem value="credit_7">⏰ Credit 7 Days</SelectItem>
                    <SelectItem value="credit_14">⏰ Credit 14 Days</SelectItem>
                    <SelectItem value="credit_30">⏰ Credit 30 Days</SelectItem>
                    <SelectItem value="credit_60">⏰ Credit 60 Days</SelectItem>
                  </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">
                  You can change the payment method if needed
                </p>
              </div>

              {formData.payment_method === 'credit' && (
                <div className="space-y-2">
                  <Label>Payment Due Date</Label>
                  <DatePicker
                    value={formData.payment_due_date}
                    onChange={(v) => setFormData({ ...formData, payment_due_date: v })}
                    placeholder="Select due date"
                  />
                </div>
              )}
            </div>

            <div className="space-y-2">
              <Label>Notes</Label>
              <Textarea
                value={formData.notes}
                onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                rows={3}
              />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle>Items</CardTitle>
              <div className="flex gap-2">
                <Button 
                  type="button" 
                  onClick={handlePriceComparison} 
                  variant="outline"
                  size="sm"
                  disabled={loadingComparison || items.filter(i => i.product_id).length === 0}
                >
                  <TrendingUp className="h-4 w-4 mr-2" />
                  {loadingComparison ? 'Loading...' : 'Check Prices'}
                </Button>
                <Button type="button" variant="outline" size="sm" onClick={addItem}>
                  <Plus className="mr-2 h-4 w-4" />
                  Add Item
                </Button>
              </div>
            </div>
          </CardHeader>
          <CardContent className="space-y-6">
            {items.map((item, index) => {
              const selectedProduct = products.find(p => p.id.toString() === item.product_id);
              const showBatchFields = selectedProduct?.track_batch || selectedProduct?.track_expiry;
              
              return (
                <div key={index} className="border rounded-lg p-4 space-y-4 bg-muted/20">
                  {/* Main Item Row */}
                  <div className="flex items-end gap-4">
                    <div className="flex-1 space-y-2">
                      <Label>Product *</Label>
                      <div className="flex gap-2">
                        <ProductSearchSelect
                          products={products}
                          value={item.product_id}
                          onChange={(v) => updateItem(index, 'product_id', v)}
                          placeholder="Select product"
                        />
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
                      {selectedProduct && (showBatchFields) && (
                        <p className="text-xs text-blue-600 dark:text-blue-400 flex items-center gap-1">
                          <Package className="h-3 w-3" />
                          {selectedProduct.track_batch && 'Batch tracking enabled'}
                          {selectedProduct.track_batch && selectedProduct.track_expiry && ' • '}
                          {selectedProduct.track_expiry && 'Expiry tracking enabled'}
                        </p>
                      )}
                    </div>

                    <div className="w-24 space-y-2">
                      <Label>Quantity *</Label>
                      <Input
                        type="number"
                        min="1"
                        value={item.quantity_ordered || ''}
                        onChange={(e) => updateItem(index, 'quantity_ordered', parseInt(e.target.value) || 0)}
                      />
                    </div>

                    <div className="w-28 space-y-2">
                      <Label>Unit Type *</Label>
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
                      <Label>Unit Cost (₦) *</Label>
                      <Input
                        type="number"
                        min="0"
                        step="0.01"
                        value={item.unit_cost || ''}
                        onChange={(e) => updateItem(index, 'unit_cost', parseFloat(e.target.value) || 0)}
                      />
                    </div>

                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      onClick={() => removeItem(index)}
                      disabled={items.length === 1}
                      title="Remove item"
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>

                  {/* Batch & Expiry Fields - Show when product has tracking enabled OR always show for flexibility */}
                  <div className="grid grid-cols-3 gap-4 pt-3 border-t">
                    <div className="space-y-2">
                      <Label className="flex items-center gap-2">
                        <Package className="h-3.5 w-3.5 text-muted-foreground" />
                        Batch Number {selectedProduct?.track_batch && <span className="text-red-500 dark:text-red-400">*</span>}
                      </Label>
                      <Input
                        type="text"
                        placeholder={selectedProduct?.track_batch ? "Required" : "Optional"}
                        value={item.batch_number || ''}
                        onChange={(e) => updateItem(index, 'batch_number', e.target.value)}
                        className={selectedProduct?.track_batch ? 'border-blue-300 dark:border-blue-800' : ''}
                      />
                      {selectedProduct?.track_batch && (
                        <p className="text-xs text-muted-foreground">
                          ⚠️ Required for this product
                        </p>
                      )}
                    </div>

                    <div className="space-y-2">
                      <Label className="flex items-center gap-2">
                        <Calendar className="h-3.5 w-3.5 text-muted-foreground" />
                        Manufacturing Date (Optional)
                      </Label>
                      <MonthYearPicker
                        value={item.manufacturing_date || ''}
                        onChange={(v) => updateItem(index, 'manufacturing_date', v)}
                        placeholder="Select month & year"
                        maxDate={new Date()}
                      />
                    </div>

                    <div className="space-y-2">
                      <Label className="flex items-center gap-2">
                        <Calendar className="h-3.5 w-3.5 text-orange-500 dark:text-orange-400" />
                        Expiry Date {selectedProduct?.track_expiry && <span className="text-red-500 dark:text-red-400">*</span>}
                      </Label>
                      <MonthYearPicker
                        value={item.expiry_date || ''}
                        onChange={(v) => updateItem(index, 'expiry_date', v)}
                        placeholder={selectedProduct?.track_expiry ? "Required" : "Optional"}
                        minDate={new Date()}
                        className={selectedProduct?.track_expiry ? 'border-orange-300 dark:border-orange-800' : ''}
                      />
                      {selectedProduct?.track_expiry && (
                        <p className="text-xs text-muted-foreground">
                          ⚠️ Required for this product
                        </p>
                      )}
                    </div>
                  </div>
                </div>
              );
            })}

            <div className="pt-4 border-t">
              <div className="flex justify-between text-lg font-semibold">
                <span>Total:</span>
                <span>₦{total.toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
              </div>
            </div>
          </CardContent>
        </Card>

        <div className="flex justify-end space-x-2">
          <Button type="button" variant="outline" onClick={() => navigate('/admin/purchase-orders')} disabled={loading}>
            Cancel
          </Button>
          <Button type="submit" disabled={loading}>
            {loading ? 'Creating...' : 'Create Purchase Order'}
          </Button>
        </div>
      </form>

      {/* Price Comparison Modal */}
      <PriceComparisonModal
        open={priceComparisonOpen}
        onClose={() => setPriceComparisonOpen(false)}
        comparisons={priceComparisons}
        currentPrices={getCurrentPrices()}
      />

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
