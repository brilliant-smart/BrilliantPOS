import { useState, useEffect } from 'react';
import { useNavigate, useParams, Link } from 'react-router-dom';
import { ArrowLeft, Save } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { DatePicker } from '@/components/DatePicker';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { fetchExpense, updateExpense, fetchExpenseCategories, type Expense, type ExpenseCategory, type UpdateExpenseData } from '@/app/api/expenses';
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

export default function ExpenseEdit() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { toast } = useToast();
  
  const [expense, setExpense] = useState<Expense | null>(null);
  const [categories, setCategories] = useState<ExpenseCategory[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  
  const [formData, setFormData] = useState<UpdateExpenseData>({});
  const [errors, setErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    loadData();
  }, [id]);

  const loadData = async () => {
    try {
      setLoading(true);
      const [expenseData, categoriesData] = await Promise.all([
        fetchExpense(parseInt(id!)),
        fetchExpenseCategories(true),
      ]);
      
      setExpense(expenseData);
      setCategories(categoriesData);
      
      // Initialize form with existing data
      setFormData({
        title: expenseData.title,
        description: expenseData.description || '',
        amount: parseFloat(expenseData.amount),
        payment_method: expenseData.payment_method,
        category_id: expenseData.category_id || undefined,
        expense_date: expenseData.expense_date,
        vendor: expenseData.vendor || '',
        receipt_number: expenseData.receipt_number || '',
        notes: expenseData.notes || '',
      });
    } catch (error) {
      console.error('Failed to load expense:', error);
      toast({
        title: 'Error',
        description: 'Failed to load expense',
        variant: 'destructive',
      });
      navigate('/admin/expenses');
    } finally {
      setLoading(false);
    }
  };

  const validateForm = (): boolean => {
    const newErrors: Record<string, string> = {};
    
    if (formData.title && !formData.title.trim()) {
      newErrors.title = 'Title is required';
    }
    
    if (formData.amount !== undefined && formData.amount <= 0) {
      newErrors.amount = 'Amount must be greater than 0';
    }
    
    if (formData.expense_date) {
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

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!validateForm()) {
      toast({
        title: 'Validation Error',
        description: 'Please fix the errors in the form',
        variant: 'destructive',
      });
      return;
    }
    
    try {
      setSaving(true);
      
      const submitData: UpdateExpenseData = {
        ...formData,
        description: formData.description || undefined,
        vendor: formData.vendor || undefined,
        receipt_number: formData.receipt_number || undefined,
        notes: formData.notes || undefined,
      };
      
      await updateExpense(parseInt(id!), submitData);
      
      toast({
        title: 'Success',
        description: 'Expense updated successfully',
      });
      
      navigate('/admin/expenses');
    } catch (error: any) {
      console.error('Failed to update expense:', error);
      
      if (error.response?.data?.errors) {
        setErrors(error.response.data.errors);
      }
      
      toast({
        title: 'Error',
        description: error.response?.data?.message || 'Failed to update expense',
        variant: 'destructive',
      });
    } finally {
      setSaving(false);
    }
  };

  const handleChange = (field: keyof UpdateExpenseData, value: any) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    
    if (errors[field]) {
      setErrors(prev => {
        const newErrors = { ...prev };
        delete newErrors[field];
        return newErrors;
      });
    }
  };

  if (loading) {
    return (
      <div className="p-4 md:p-6">
        <div className="text-center py-12">
          <p className="text-muted-foreground">Loading expense...</p>
        </div>
      </div>
    );
  }

  if (!expense) {
    return null;
  }

  return (
    <div className="p-4 md:p-6 max-w-4xl mx-auto space-y-6">
      <div className="flex items-center gap-4">
        <Link to="/admin/expenses">
          <Button variant="ghost" size="icon">
            <ArrowLeft className="h-5 w-5" />
          </Button>
        </Link>
        <div>
          <h1 className="text-3xl font-bold">Edit Expense</h1>
          <p className="text-muted-foreground">{expense.expense_number}</p>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle>Basic Information</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="title">Title <span className="text-destructive">*</span></Label>
                <Input
                  id="title"
                  value={formData.title || ''}
                  onChange={(e) => handleChange('title', e.target.value)}
                  className={errors.title ? 'border-destructive' : ''}
                />
                {errors.title && <p className="text-sm text-destructive">{errors.title}</p>}
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
                          <div className="w-3 h-3 rounded-full" style={{ backgroundColor: cat.color }} />
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
                rows={3}
                value={formData.description || ''}
                onChange={(e) => handleChange('description', e.target.value)}
              />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Amount & Payment</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="amount">Amount (₦) <span className="text-destructive">*</span></Label>
                <Input
                  id="amount"
                  type="number"
                  step="0.01"
                  min="0.01"
                  value={formData.amount || ''}
                  onChange={(e) => handleChange('amount', parseFloat(e.target.value) || 0)}
                  className={errors.amount ? 'border-destructive' : ''}
                />
                {errors.amount && <p className="text-sm text-destructive">{errors.amount}</p>}
              </div>

              <div className="space-y-2">
                <Label htmlFor="expense_date">Expense Date <span className="text-destructive">*</span></Label>
                <DatePicker
                  value={formData.expense_date}
                  onChange={(value) => handleChange('expense_date', value)}
                  maxDate={new Date()}
                  className={errors.expense_date ? 'border-destructive' : ''}
                />
                {errors.expense_date && <p className="text-sm text-destructive">{errors.expense_date}</p>}
              </div>
            </div>

            <div className="space-y-2">
              <Label>Payment Method <span className="text-destructive">*</span></Label>
              <RadioGroup
                value={formData.payment_method || 'cash'}
                onValueChange={(value) => handleChange('payment_method', value as any)}
                className="grid grid-cols-2 md:grid-cols-3 gap-4"
              >
                {PAYMENT_METHODS.map((method) => (
                  <div key={method.value}>
                    <RadioGroupItem value={method.value} id={`edit-${method.value}`} className="peer sr-only" />
                    <Label
                      htmlFor={`edit-${method.value}`}
                      className="flex items-center gap-2 p-4 rounded-md border-2 border-muted bg-popover hover:bg-accent hover:text-accent-foreground peer-data-[state=checked]:border-primary cursor-pointer"
                    >
                      {method.icon}
                      <span>{method.label}</span>
                    </Label>
                  </div>
                ))}
              </RadioGroup>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Vendor & Receipt Information</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="vendor">Vendor/Supplier</Label>
                <Input
                  id="vendor"
                  value={formData.vendor || ''}
                  onChange={(e) => handleChange('vendor', e.target.value)}
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="receipt_number">Receipt Number</Label>
                <Input
                  id="receipt_number"
                  value={formData.receipt_number || ''}
                  onChange={(e) => handleChange('receipt_number', e.target.value)}
                />
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="notes">Internal Notes</Label>
              <Textarea
                id="notes"
                rows={2}
                value={formData.notes || ''}
                onChange={(e) => handleChange('notes', e.target.value)}
              />
            </div>
          </CardContent>
        </Card>

        <div className="flex space-y-2">
          <div className="text-sm text-muted-foreground">
            <p>Recorded by: {expense.recorder?.name}</p>
            <p>Created: {new Date(expense.created_at).toLocaleString()}</p>
            <p>Last updated: {new Date(expense.updated_at).toLocaleString()}</p>
          </div>
        </div>

        <div className="flex items-center justify-between gap-4">
          <Link to="/admin/expenses">
            <Button type="button" variant="ghost">Cancel</Button>
          </Link>
          
          <Button type="submit" disabled={saving} className="gap-2">
            {saving ? (
              <><span className="animate-spin">⏳</span> Updating...</>
            ) : (
              <><Save className="h-4 w-4" /> Update Expense</>
            )}
          </Button>
        </div>
      </form>
    </div>
  );
}
