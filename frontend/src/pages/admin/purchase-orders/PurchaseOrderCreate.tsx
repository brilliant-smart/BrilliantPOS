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
import { Supplier, ProductUnitType } from '@/types/ims';
import { toast } from 'sonner';
import BarcodeScanner from '@/components/BarcodeScanner';
import { MonthYearPicker } from '@/components/MonthYearPicker';
import { DatePicker } from '@/components/DatePicker';
import PriceComparisonModal from '@/components/PriceComparisonModal';
import { createBarcodeScanner } from '@/utils/barcodeScanner';
import { todayLocal } from '@/utils/date';
import { ProductSearchSelect } from '@/components/ProductSearchSelect';

interface POLineItem {
  product_id: string;
  quantity_ordered: number;
  unit_type: string;
  product_unit_type_id: number | null;
  conversion_factor: number;
  unit_cost: number;
  batch_number: string;
  expiry_date: string;
  manufacturing_date: string;
}

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
    order_date: todayLocal(),
    expected_delivery_date: '',
    payment_method: 'credit' as 'cash' | 'bank_transfer' | 'cheque' | 'card' | 'credit',
    payment_due_date: '',
    notes: '',
  });
  const [items, setItems] = useState<POLineItem[]>([
    {
      product_id: '',
      quantity_ordered: 1,
      unit_type: 'piece',
      product_unit_type_id: null,
      conversion_factor: 1,
      unit_cost: 0,
      batch_number: '',
      expiry_date: '',
      manufacturing_date: '',
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

            // Check if the barcode matches a unit type barcode
            let matchedUnitType: ProductUnitType | null = null;
            if (product.unit_types) {
              for (const ut of product.unit_types) {
                if (ut.barcodes?.some(b => b.barcode === barcode)) {
                  matchedUnitType = ut;
                  break;
                }
              }
            }

            // Find first empty row or add new row
            const emptyIndex = items.findIndex(item => !item.product_id);
            if (emptyIndex !== -1) {
              const newItems = [...items];
              newItems[emptyIndex] = {
                ...newItems[emptyIndex],
                product_id: product.id.toString(),
                unit_cost: Number(product.cost_price) || 0,
                unit_type: matchedUnitType ? matchedUnitType.name.toLowerCase() : (product.unit_type || 'piece'),
                product_unit_type_id: matchedUnitType ? matchedUnitType.id : (product.unit_types?.length ? (product.unit_types.find(ut => ut.is_base)?.id || null) : null),
                conversion_factor: matchedUnitType ? matchedUnitType.conversion_factor : (product.unit_types?.length ? (product.unit_types.find(ut => ut.is_base)?.conversion_factor || 1) : 1),
              };
              setItems(newItems);
            } else {
              setItems([...items, {
                product_id: product.id.toString(),
                quantity_ordered: 1,
                unit_cost: Number(product.cost_price) || 0,
                unit_type: matchedUnitType ? matchedUnitType.name.toLowerCase() : (product.unit_type || 'piece'),
                product_unit_type_id: matchedUnitType ? matchedUnitType.id : (product.unit_types?.length ? (product.unit_types.find(ut => ut.is_base)?.id || null) : null),
                conversion_factor: matchedUnitType ? matchedUnitType.conversion_factor : (product.unit_types?.length ? (product.unit_types.find(ut => ut.is_base)?.conversion_factor || 1) : 1),
                manufacturing_date: '',
                expiry_date: '',
                batch_number: '',
              }]);
            }

            toast.success(`Added: ${product.name}${matchedUnitType ? ` (${matchedUnitType.name})` : ''}`);
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
      const result = await supplierApi.getAll();
      setSuppliers(result.data.filter((s: Supplier) => s.is_active));
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
      unit_type: 'piece',
      product_unit_type_id: null,
      conversion_factor: 1,
      unit_cost: 0,
      batch_number: '',
      expiry_date: '',
      manufacturing_date: '',
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

  const handleProductSelect = (index: number, productId: string) => {
    const product = products.find(p => p.id.toString() === productId);
    const updated = [...items];
    const costPrice = Number(product?.cost_price) || 0;

    updated[index] = {
      ...updated[index],
      product_id: productId,
      unit_cost: costPrice,
    };

    // If product has more than one unit type, default to the base unit type
    if (product?.unit_types && product.unit_types.length > 1) {
      const baseUnit = product.unit_types.find(ut => ut.is_base) || product.unit_types[0];
      updated[index].unit_type = baseUnit.name.toLowerCase();
      updated[index].product_unit_type_id = baseUnit.id;
      updated[index].conversion_factor = baseUnit.conversion_factor;
    } else if (product?.unit_types && product.unit_types.length === 1) {
      // Product has only base unit type — default to Piece
      const baseUnit = product.unit_types[0];
      updated[index].unit_type = baseUnit.name.toLowerCase();
      updated[index].product_unit_type_id = baseUnit.id;
      updated[index].conversion_factor = 1;
    } else {
      updated[index].unit_type = product?.unit_type || 'piece';
      updated[index].product_unit_type_id = null;
      updated[index].conversion_factor = 1;
    }

    setItems(updated);
  };

  const handleUnitTypeSelect = (index: number, unitTypeId: number) => {
    const product = products.find(p => p.id.toString() === items[index].product_id);
    if (!product?.unit_types) return;

    const unitType = product.unit_types.find(ut => ut.id === unitTypeId);
    if (!unitType) return;

    const updated = [...items];
    const currentUnitCost = Number(updated[index].unit_cost) || 0;
    const currentConversionFactor = updated[index].conversion_factor || 1;

    // Calculate per-piece cost from current unit cost and conversion factor
    const perPieceCost = currentConversionFactor > 0 ? currentUnitCost / currentConversionFactor : 0;

    // Recalculate unit cost for the new unit type
    const newConversionFactor = unitType.conversion_factor;
    const newUnitCost = perPieceCost > 0 ? Math.round(perPieceCost * newConversionFactor * 100) / 100 : 0;

    updated[index] = {
      ...updated[index],
      unit_type: unitType.name.toLowerCase(),
      product_unit_type_id: unitType.id,
      conversion_factor: newConversionFactor,
      unit_cost: newUnitCost || currentUnitCost,
    };
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
        // Check if the barcode matches a unit type barcode
        let matchedUnitType: ProductUnitType | null = null;
        if (product.unit_types) {
          for (const ut of product.unit_types) {
            if (ut.barcodes?.some(b => b.barcode === barcode)) {
              matchedUnitType = ut;
              break;
            }
          }
        }

        const updated = [...items];
        updated[scanningForIndex] = {
          ...updated[scanningForIndex],
          product_id: product.id.toString(),
          unit_cost: Number(product.cost_price) || 0,
          unit_type: matchedUnitType ? matchedUnitType.name.toLowerCase() : (product.unit_types?.length ? (product.unit_types.find(ut => ut.is_base)?.name.toLowerCase() || 'piece') : (product.unit_type || 'piece')),
          product_unit_type_id: matchedUnitType ? matchedUnitType.id : (product.unit_types?.length ? (product.unit_types.find(ut => ut.is_base)?.id || null) : null),
          conversion_factor: matchedUnitType ? matchedUnitType.conversion_factor : (product.unit_types?.length ? (product.unit_types.find(ut => ut.is_base)?.conversion_factor || 1) : 1),
        };
        setItems(updated);

        toast.success(`Product "${product.name}" added${matchedUnitType ? ` (${matchedUnitType.name})` : ''}`);
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
        items: items.map(item => ({
          product_id: parseInt(item.product_id),
          quantity_ordered: item.quantity_ordered,
          unit_cost: item.unit_cost,
          unit_type: item.unit_type,
          product_unit_type_id: item.product_unit_type_id,
          conversion_factor: item.conversion_factor,
          batch_number: item.batch_number || undefined,
          expiry_date: item.expiry_date || undefined,
          manufacturing_date: item.manufacturing_date || undefined,
        })),
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
    <div className="p-4 md:p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div className="flex items-center space-x-4">
          <Button variant="ghost" size="icon" onClick={() => navigate('/admin/purchase-orders')}>
            <ArrowLeft className="h-4 w-4" />
          </Button>
          <div>
            <h1 className="text-3xl font-bold">Create Purchase Order</h1>
            <p className="text-muted-foreground dark:text-muted-foreground/80">Order stock from suppliers</p>
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
                        <span className="text-xs text-muted-foreground dark:text-muted-foreground/80 ml-2">
                          ({s.payment_terms?.replace('_', ' ') || 'credit'})
                        </span>
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {formData.supplier_id && (
                  <p className="text-xs text-muted-foreground dark:text-muted-foreground/80">
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
                <p className="text-xs text-muted-foreground dark:text-muted-foreground/80">
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
                          onChange={(v) => handleProductSelect(index, v)}
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

                    <div className="w-40 space-y-2">
                      <Label>Unit Type *</Label>
                      {selectedProduct?.unit_types && selectedProduct.unit_types.length > 1 ? (
                        <Select
                          value={item.product_unit_type_id?.toString() || ''}
                          onValueChange={(v) => handleUnitTypeSelect(index, parseInt(v))}
                        >
                          <SelectTrigger>
                            <SelectValue placeholder="Select unit" />
                          </SelectTrigger>
                          <SelectContent>
                            {selectedProduct.unit_types
                              .sort((a: ProductUnitType, b: ProductUnitType) => a.sort_order - b.sort_order)
                              .map((ut: ProductUnitType) => (
                                <SelectItem key={ut.id} value={ut.id.toString()}>
                                  {ut.name} {ut.is_base ? '(base)' : `(${ut.conversion_factor} pcs)`}
                                </SelectItem>
                              ))}
                          </SelectContent>
                        </Select>
                      ) : (
                        <Select
                          value={item.product_unit_type_id
                            ? `ut_${item.product_unit_type_id}`
                            : item.unit_type || 'piece'}
                          onValueChange={(v) => {
                            if (v.startsWith('ut_')) {
                              const unitTypeId = parseInt(v.replace('ut_', ''));
                              handleUnitTypeSelect(index, unitTypeId);
                            } else {
                              // Standard unit type — set conversion factor based on selection
                              const conversionMap: Record<string, number> = {
                                piece: 1, carton: 12, box: 24, pack: 6, dozen: 12,
                                bag: 10, crate: 24, bundle: 20, sack: 50, kg: 1, liter: 1, meter: 1,
                              };
                              const factor = conversionMap[v] || 1;
                              const baseUnitTypeId = selectedProduct?.unit_types?.find(ut => ut.is_base)?.id || null;

                              // Recalculate unit cost based on per-piece cost
                              const currentUnitCost = Number(item.unit_cost) || 0;
                              const currentConversionFactor = item.conversion_factor || 1;
                              const perPieceCost = currentConversionFactor > 0 ? currentUnitCost / currentConversionFactor : 0;
                              const newUnitCost = perPieceCost > 0 ? Math.round(perPieceCost * factor * 100) / 100 : currentUnitCost;

                              const updated = [...items];
                              updated[index] = {
                                ...updated[index],
                                unit_type: v,
                                product_unit_type_id: v === 'piece' ? baseUnitTypeId : null,
                                conversion_factor: factor,
                                unit_cost: newUnitCost,
                              };
                              setItems(updated);
                            }
                          }}
                        >
                          <SelectTrigger>
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="piece">Piece</SelectItem>
                            <SelectItem value="carton">Carton</SelectItem>
                            <SelectItem value="box">Box</SelectItem>
                            <SelectItem value="pack">Pack</SelectItem>
                            <SelectItem value="dozen">Dozen</SelectItem>
                            <SelectItem value="bag">Bag</SelectItem>
                            <SelectItem value="crate">Crate</SelectItem>
                            <SelectItem value="bundle">Bundle</SelectItem>
                            <SelectItem value="sack">Sack</SelectItem>
                            <SelectItem value="kg">Kg</SelectItem>
                            <SelectItem value="liter">Liter</SelectItem>
                            <SelectItem value="meter">Meter</SelectItem>
                          </SelectContent>
                        </Select>
                      )}
                    </div>

                    {item.conversion_factor > 1 && !item.product_unit_type_id && (
                      <div className="w-24 space-y-2">
                        <Label>Pcs/Unit</Label>
                        <Input
                          type="number"
                          min="1"
                          value={item.conversion_factor || ''}
                          onChange={(e) => updateItem(index, 'conversion_factor', parseFloat(e.target.value) || 1)}
                        />
                      </div>
                    )}

                    <div className="w-36 space-y-2">
                      <Label>Unit Cost (₦) *</Label>
                      <Input
                        type="number"
                        min="0"
                        step="0.01"
                        value={item.unit_cost || ''}
                        onChange={(e) => updateItem(index, 'unit_cost', parseFloat(e.target.value) || 0)}
                      />
                      {item.conversion_factor > 1 && item.unit_cost > 0 && (
                        <p className="text-xs text-green-600 dark:text-green-400 mt-1">
                          = ₦{(item.unit_cost / item.conversion_factor).toFixed(2)}/piece
                        </p>
                      )}
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

                  {/* Conversion factor info */}
                  {item.conversion_factor > 1 && (
                    <div className="flex items-center gap-3 text-sm text-muted-foreground dark:text-muted-foreground/80 bg-blue-50 dark:bg-blue-950/30 px-3 py-2 rounded-md border border-blue-200 dark:border-blue-800">
                      <span className="font-medium">
                        1 {selectedProduct?.unit_types?.find((ut: ProductUnitType) => ut.id === item.product_unit_type_id)?.name || item.unit_type} = {item.conversion_factor} Pieces
                      </span>
                      {item.unit_cost > 0 && (
                        <span className="text-green-600 dark:text-green-400">
                          (₦{item.unit_cost.toFixed(2)} per {selectedProduct?.unit_types?.find((ut: ProductUnitType) => ut.id === item.product_unit_type_id)?.name || item.unit_type} = ₦{(item.unit_cost / item.conversion_factor).toFixed(2)}/piece)
                        </span>
                      )}
                    </div>
                  )}

                  {/* Batch & Expiry Fields - Show when product has tracking enabled OR always show for flexibility */}
                  <div className="grid grid-cols-3 gap-4 pt-3 border-t">
                    <div className="space-y-2">
                      <Label className="flex items-center gap-2">
                        <Package className="h-3.5 w-3.5 text-muted-foreground dark:text-muted-foreground/80" />
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
                        <p className="text-xs text-muted-foreground dark:text-muted-foreground/80">
                          ⚠️ Required for this product
                        </p>
                      )}
                    </div>

                    <div className="space-y-2">
                      <Label className="flex items-center gap-2">
                        <Calendar className="h-3.5 w-3.5 text-muted-foreground dark:text-muted-foreground/80" />
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
                        <p className="text-xs text-muted-foreground dark:text-muted-foreground/80">
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
