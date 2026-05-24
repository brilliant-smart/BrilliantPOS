import { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { ArrowLeft, Save, PlusCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { DatePicker } from '@/components/DatePicker';
import { todayLocal } from '@/utils/date';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { createExpense, fetchExpenseCategories, type ExpenseCategory, type CreateExpenseData } from '@/app/api/expenses';
import { useToast } from '@/hooks/use-toast';
import { Banknote, Building2, CreditCard, User, Store, MoreHorizontal } from 'lucide-react';

const PAYMENT_METHODS = [
  { value: 'cash', label: 'Cash', icon: <Banknote className="h-4 w-4" /> },
  { value: 'bank_transfer', label: 'Bank Transfer', icon: <Building2 className="h-4 w-4" /> },
  { value: 'pos_terminal', label: 'POS Terminal', icon: <CreditCard className="h-4 w-4" /> },
  { value: 'personal_payment', label: 'Personal Payment', icon: <User className="h-4 w-4" /> },
  { value: 'shop_account', label: 'Shop Account', icon: <Store className="h-4 w-4" /> },
  { value: 'other', label: 'Other', icon: <MoreHorizontal className="h-4 w-4" /> },
];

export default function ExpenseCreate() {
  const navigate = useNavigate();
  const { toast } = useToast();
  
  const [categories, setCategories] = useState<ExpenseCategory[]>([]);
  const [loading, setLoading] = useState(false);
  const [saveAndAddAnother, setSaveAndAddAnother] = useState(false);
  
  // Form data
  const [formData, setFormData] = useState<CreateExpenseData>({
    title: '',
    description: '',
    amount: 0,
    payment_method: 'cash',
    category_id: undefined,
    expense_date: todayLocal(),
    vendor: '',
    receipt_number: '',
    notes: '',
  });
  
  const [errors, setErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    loadCategories();
  }, []);

  const loadCategories = async () => {
    try {
      const data = await fetchExpenseCategories(true);
      setCategories(data);
    } catch (error) {
      console.error('Failed to load categories:', error);
      toast({
        title: 'Error',
        description: 'Failed to load expense categories',
        variant: 'destructive',
      });
    }
  };

  const validateForm = (): boolean => {
    const newErrors: Record<string, string> = {};
    
    if (!formData.title.trim()) {
      newErrors.title = 'Title is required';
    }
    
    if (!formData.amount || formData.amount <= 0) {
      newErrors.amount = 'Amount must be greater than 0';
    }
    
    if (!formData.payment_method) {
      newErrors.payment_method = 'Payment method is required';
    }
    
    if (!formData.expense_date) {
      newErrors.expense_date = 'Expense date is required';
    } else {
      // Compare YYYY-MM-DD strings directly to avoid timezone issues
      const today = new Date();
      const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

      if (formData.expense_date > todayStr) {
        newErrors.expense_date = 'Expense date cannot be in the future';
      }
    }
    
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (addAnother: boolean = false) => {
    if (!validateForm()) {
      toast({
        title: 'Validation Error',
        description: 'Please fix the errors in the form',
        variant: 'destructive',
      });
      return;
    }
    
    try {
      setLoading(true);
      setSaveAndAddAnother(addAnother);
      
      const submitData: CreateExpenseData = {
        ...formData,
        description: formData.description || undefined,
        vendor: formData.vendor || undefined,
        receipt_number: formData.receipt_number || undefined,
        notes: formData.notes || undefined,
      };
      
      await createExpense(submitData);
      
      toast({
        title: 'Success',
        description: 'Expense recorded successfully',
      });
      
      if (addAnother) {
        // Reset form for another entry
        setFormData({
          title: '',
          description: '',
          amount: 0,
          payment_method: 'cash',
          category_id: undefined,
          expense_date: todayLocal(),
          vendor: '',
          receipt_number: '',
          notes: '',
        });
        setErrors({});
      } else {
        navigate('/admin/expenses');
      }
    } catch (error: any) {
      console.error('Failed to create expense:', error);
      
      if (error.response?.data?.errors) {
        setErrors(error.response.data.errors);
      }
      
      toast({
        title: 'Error',
        description: error.response?.data?.message || 'Failed to create expense',
        variant: 'destructive',
      });
    } finally {
      setLoading(false);
      setSaveAndAddAnother(false);
    }
  };

  const handleChange = (field: keyof CreateExpenseData, value: any) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    
    // Clear error for this field
    if (errors[field]) {
      setErrors(prev => {
        const newErrors = { ...prev };
        delete newErrors[field];
        return newErrors;
      });
    }
  };

  return (
    <div className="p-4 md:p-6 max-w-4xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center gap-4">
        <Link to="/admin/expenses">
          <Button variant="ghost" size="icon">
            <ArrowLeft className="h-5 w-5" />
          </Button>
        </Link>
        <div>
          <h1 className="text-3xl font-bold">Add Expense</h1>
          <p className="text-muted-foreground">Record a new shop expense</p>
        </div>
      </div>

      <form onSubmit={(e) => { e.preventDefault(); handleSubmit(false); }} className="space-y-6">
        {/* Basic Information */}
        <Card>
          <CardHeader>
            <CardTitle>Basic Information</CardTitle>
            <CardDescription>Enter the expense details</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="title">
                  Title <span className="text-destructive">*</span>
                </Label>
                <Input
                  id="title"
                  placeholder="e.g., Generator Fuel Purchase"
                  value={formData.title}
                  onChange={(e) => handleChange('title', e.target.value)}
                  className={errors.title ? 'border-destructive' : ''}
                />
                {errors.title && (
                  <p className="text-sm text-destructive">{errors.title}</p>
                )}
              </div>

              <div className="space-y-2">
                <Label htmlFor="category">Category</Label>
                <Select
                  value={formData.category_id?.toString() || 'none'}
                  onValueChange={(value) => handleChange('category_id', value === 'none' ? undefined : parseInt(value))}
                >
                  <SelectTrigger id="category">
                    <SelectValue placeholder="Select category" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">None</SelectItem>
                    {categories.map((cat) => (
                      <SelectItem key={cat.id} value={cat.id.toString()}>
                        <div className="flex items-center gap-2">
                          <div
                            className="w-3 h-3 rounded-full"
                            style={{ backgroundColor: cat.color }}
                          />
                          {cat.name}
                        </div>
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="description">Description</Label>
              <Textarea
                id="description"
                placeholder="Detailed description of the expense..."
                rows={3}
                value={formData.description}
                onChange={(e) => handleChange('description', e.target.value)}
              />
            </div>
          </CardContent>
        </Card>

        {/* Amount & Payment */}
        <Card>
          <CardHeader>
            <CardTitle>Amount & Payment</CardTitle>
            <CardDescription>Specify the expense amount and payment method</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="amount">
                  Amount (₦) <span className="text-destructive">*</span>
                </Label>
                <Input
                  id="amount"
                  type="number"
                  placeholder="0.00"
                  step="0.01"
                  min="0.01"
                  value={formData.amount || ''}
                  onChange={(e) => handleChange('amount', parseFloat(e.target.value) || 0)}
                  className={errors.amount ? 'border-destructive' : ''}
                />
                {errors.amount && (
                  <p className="text-sm text-destructive">{errors.amount}</p>
                )}
              </div>

              <div className="space-y-2">
                <Label htmlFor="expense_date">
                  Expense Date <span className="text-destructive">*</span>
                </Label>
                <DatePicker
                  value={formData.expense_date}
                  onChange={(value) => handleChange('expense_date', value)}
                  maxDate={new Date()}
                  className={errors.expense_date ? 'border-destructive' : ''}
                />
                {errors.expense_date && (
                  <p className="text-sm text-destructive">{errors.expense_date}</p>
                )}
              </div>
            </div>

            <div className="space-y-2">
              <Label>
                Payment Method <span className="text-destructive">*</span>
              </Label>
              <RadioGroup
                value={formData.payment_method}
                onValueChange={(value) => handleChange('payment_method', value as any)}
                className="grid grid-cols-2 md:grid-cols-3 gap-4"
              >
                {PAYMENT_METHODS.map((method) => (
                  <div key={method.value}>
                    <RadioGroupItem
                      value={method.value}
                      id={method.value}
                      className="peer sr-only"
                    />
                    <Label
                      htmlFor={method.value}
                      className="flex items-center gap-2 p-4 rounded-md border-2 border-muted bg-popover hover:bg-accent hover:text-accent-foreground peer-data-[state=checked]:border-primary cursor-pointer"
                    >
                      {method.icon}
                      <span>{method.label}</span>
                    </Label>
                  </div>
                ))}
              </RadioGroup>
              {errors.payment_method && (
                <p className="text-sm text-destructive">{errors.payment_method}</p>
              )}
            </div>
          </CardContent>
        </Card>

        {/* Vendor & Receipt */}
        <Card>
          <CardHeader>
            <CardTitle>Vendor & Receipt Information</CardTitle>
            <CardDescription>Optional details for record keeping</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="vendor">Vendor/Supplier</Label>
                <Input
                  id="vendor"
                  placeholder="e.g., BEDC Electricity"
                  value={formData.vendor}
                  onChange={(e) => handleChange('vendor', e.target.value)}
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="receipt_number">Receipt Number</Label>
                <Input
                  id="receipt_number"
                  placeholder="e.g., REC-2026-001"
                  value={formData.receipt_number}
                  onChange={(e) => handleChange('receipt_number', e.target.value)}
                />
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="notes">Internal Notes</Label>
              <Textarea
                id="notes"
                placeholder="Additional notes or comments..."
                rows={2}
                value={formData.notes}
                onChange={(e) => handleChange('notes', e.target.value)}
              />
            </div>
          </CardContent>
        </Card>

        {/* Action Buttons */}
        <div className="flex items-center justify-between gap-4">
          <Link to="/admin/expenses">
            <Button type="button" variant="ghost">
              Cancel
            </Button>
          </Link>
          
          <div className="flex gap-2">
            <Button
              type="button"
              variant="outline"
              onClick={() => handleSubmit(true)}
              disabled={loading}
              className="gap-2"
            >
              {loading && saveAndAddAnother ? (
                <>
                  <span className="animate-spin">⏳</span>
                  Saving...
                </>
              ) : (
                <>
                  <PlusCircle className="h-4 w-4" />
                  Save & Add Another
                </>
              )}
            </Button>
            
            <Button
              type="submit"
              disabled={loading}
              className="gap-2"
            >
              {loading && !saveAndAddAnother ? (
                <>
                  <span className="animate-spin">⏳</span>
                  Saving...
                </>
              ) : (
                <>
                  <Save className="h-4 w-4" />
                  Save Expense
                </>
              )}
            </Button>
          </div>
        </div>
      </form>
    </div>
  );
}
