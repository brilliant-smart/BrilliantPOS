import { useState, useEffect, useMemo } from "react";
import { useAuth } from "@/app/auth/AuthContext";
import { getProducts, deleteProduct } from "@/app/api/products";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Badge } from "@/components/ui/badge";
import { useToast } from "@/hooks/use-toast";
import { Pencil, Trash2, Plus, Loader2, Search, Star, ArrowUpDown, CheckSquare, Square, Download, Package, History, Scan, Calendar, AlertTriangle, X } from "lucide-react";
import BarcodeScanner from "@/components/BarcodeScanner";
import { createProduct, updateProduct } from "@/app/api/products";
import { Checkbox } from "@/components/ui/checkbox";
import { StockBadge } from "@/components/StockBadge";
import { StockAdjustmentModal } from "@/components/StockAdjustmentModal";
import { StockHistoryModal } from "@/components/StockHistoryModal";
import { MonthYearPicker } from '@/components/MonthYearPicker';
import { Switch } from "@/components/ui/switch";
import { Product } from "@/types/ims";

export default function ProductList() {
  const { user } = useAuth();
  const { toast } = useToast();

  const [products, setProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [productToDelete, setProductToDelete] = useState<Product | null>(null);
  const [formDialogOpen, setFormDialogOpen] = useState(false);
  const [editingProduct, setEditingProduct] = useState<Product | null>(null);
  const [submitting, setSubmitting] = useState(false);

  // Search and Filter state
  const [searchQuery, setSearchQuery] = useState("");
  const [sortBy, setSortBy] = useState<string>("newest");
  const [featuredFilter, setFeaturedFilter] = useState<string>("all");

  // Bulk operations state
  const [selectedProducts, setSelectedProducts] = useState<number[]>([]);
  const [bulkDeleteDialogOpen, setBulkDeleteDialogOpen] = useState(false);

  // Stock management state
  const [stockModalOpen, setStockModalOpen] = useState(false);
  const [stockHistoryModalOpen, setStockHistoryModalOpen] = useState(false);
  const [selectedProductForStock, setSelectedProductForStock] = useState<Product | null>(null);

  // Barcode scanner state
  const [showBarcodeScanner, setShowBarcodeScanner] = useState(false);

  // Form state
  const [formData, setFormData] = useState({
    name: "",
    slug: "",
    sku: "",
    barcodes: [] as string[],
    description: "",
    price: "",
    is_active: true,
    track_batch: false,
    track_expiry: false,
    batch_number: "",
    expiry_date: "",
    manufacturing_date: "",
  });
  const [imageFile, setImageFile] = useState<File | null>(null);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      setLoading(true);
      const productsRes = await getProducts();

      let allProducts = productsRes.data.data || productsRes.data;

      setProducts(allProducts);
    } catch (error) {
      toast({
        title: "Error",
        description: "Failed to load products",
        variant: "destructive",
      });
    } finally {
      setLoading(false);
    }
  };

  // Filtered and sorted products
  const filteredProducts = useMemo(() => {
    let filtered = products;

    // Apply search filter
    if (searchQuery.trim()) {
      filtered = filtered.filter((product) =>
        product.name.toLowerCase().includes(searchQuery.toLowerCase())
      );
    }

    // Apply featured filter
    if (featuredFilter === "featured") {
      filtered = filtered.filter((product) => product.is_featured);
    } else if (featuredFilter === "regular") {
      filtered = filtered.filter((product) => !product.is_featured);
    }

    // Apply sorting
    const sorted = [...filtered].sort((a, b) => {
      switch (sortBy) {
        case "newest":
          return b.id - a.id;
        case "oldest":
          return a.id - b.id;
        case "price-low":
          return a.price - b.price;
        case "price-high":
          return b.price - a.price;
        case "name-asc":
          return a.name.localeCompare(b.name);
        case "name-desc":
          return b.name.localeCompare(a.name);
        default:
          return 0;
      }
    });

    return sorted;
  }, [products, searchQuery, featuredFilter, sortBy]);

  // Keyboard shortcuts - placed after filteredProducts is defined
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      // Ctrl+A or Cmd+A to select all (only when not in input fields)
      if ((e.ctrlKey || e.metaKey) && e.key === 'a' &&
          !['INPUT', 'TEXTAREA'].includes((e.target as HTMLElement).tagName)) {
        e.preventDefault();
        if (filteredProducts.length > 0) {
          setSelectedProducts(filteredProducts.map(p => p.id));
          toast({
            title: "All Products Selected",
            description: `${filteredProducts.length} product${filteredProducts.length !== 1 ? 's' : ''} selected`,
          });
        }
      }

      // Escape to clear selection
      if (e.key === 'Escape' && selectedProducts.length > 0) {
        setSelectedProducts([]);
        toast({
          title: "Selection Cleared",
          description: "All products deselected",
        });
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [filteredProducts, selectedProducts, toast]);

  const handleToggleFeatured = async (product: Product) => {
    try {
      const formData = new FormData();
      formData.append('_method', 'PUT');
      formData.append('is_featured', !product.is_featured ? '1' : '0');

      await updateProduct(product.id, formData);

      toast({
        title: "Success",
        description: `Product ${!product.is_featured ? 'featured' : 'unfeatured'} successfully`,
      });
      loadData();
    } catch (error: any) {
      toast({
        title: "Error",
        description: error.response?.data?.message || "Failed to update product",
        variant: "destructive",
      });
    }
  };

  const handleDelete = async () => {
    if (!productToDelete) return;

    try {
      await deleteProduct(productToDelete.id);
      toast({
        title: "Success",
        description: "Product deleted successfully",
      });
      loadData();
    } catch (error: any) {
      toast({
        title: "Error",
        description: error.response?.data?.message || "Failed to delete product",
        variant: "destructive",
      });
    } finally {
      setDeleteDialogOpen(false);
      setProductToDelete(null);
    }
  };

  const openCreateDialog = () => {
    setEditingProduct(null);
    setFormData({
      name: "",
      slug: "",
      sku: "",
      barcodes: [] as string[],
      description: "",
      price: "",
      is_active: true,
      track_batch: false,
      track_expiry: false,
      batch_number: "",
      expiry_date: "",
      manufacturing_date: "",
    });
    setImageFile(null);
    setFormDialogOpen(true);
  };

  const openEditDialog = (product: Product) => {
    setEditingProduct(product);
    setFormData({
      name: product.name,
      slug: product.slug,
      sku: product.sku || "",
      barcodes: (product.barcodes || []).map((b: any) => b.barcode),
      description: product.description || "",
      price: String(product.price),
      is_active: product.is_active,
      track_batch: product.track_batch || false,
      track_expiry: product.track_expiry || false,
      batch_number: product.batch_number || "",
      expiry_date: product.expiry_date || "",
      manufacturing_date: product.manufacturing_date || "",
    });
    setImageFile(null);
    setFormDialogOpen(true);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    // Client-side validation
    if (!formData.name.trim()) {
      toast({
        title: "Validation Error",
        description: "Product name is required",
        variant: "destructive",
      });
      return;
    }

    if (!formData.slug.trim()) {
      toast({
        title: "Validation Error",
        description: "Product slug is required",
        variant: "destructive",
      });
      return;
    }

    if (!formData.price || parseFloat(formData.price) <= 0) {
      toast({
        title: "Validation Error",
        description: "Please enter a valid price greater than 0",
        variant: "destructive",
      });
      return;
    }

    setSubmitting(true);

    try {
      const formDataToSend = new FormData();
      formDataToSend.append("name", formData.name.trim());
      formDataToSend.append("slug", formData.slug.trim());
      if (formData.sku) formDataToSend.append("sku", formData.sku.trim());
      // Send barcodes array — always include the key so backend knows to sync even when empty
      formDataToSend.append("_has_barcodes", "1");
      formData.barcodes.forEach((barcode: string) => {
        if (barcode.trim()) {
          formDataToSend.append("barcodes[]", barcode.trim());
        }
      });
      formDataToSend.append("description", formData.description.trim());
      formDataToSend.append("price", formData.price);
      formDataToSend.append("is_active", formData.is_active ? "1" : "0");

      formDataToSend.append("track_batch", formData.track_batch ? "1" : "0");
      formDataToSend.append("track_expiry", formData.track_expiry ? "1" : "0");

      // Only include batch/expiry data if editing and values are provided
      if (editingProduct) {
        if (formData.batch_number) formDataToSend.append("batch_number", formData.batch_number.trim());
        if (formData.expiry_date) formDataToSend.append("expiry_date", formData.expiry_date);
        if (formData.manufacturing_date) formDataToSend.append("manufacturing_date", formData.manufacturing_date);
      }

      if (imageFile) {
        formDataToSend.append("image", imageFile);
      }

      if (editingProduct) {
        await updateProduct(editingProduct.id, formDataToSend);
        toast({
          title: "Success",
          description: "Product updated successfully",
        });
      } else {
        await createProduct(formDataToSend);
        toast({
          title: "Success",
          description: "Product created successfully",
        });
      }

      setFormDialogOpen(false);
      loadData();
    } catch (error: any) {
      console.error('Product save error:', error.response?.data);
      const errorMsg = error.response?.data?.message || 'Failed to save product';
      const validationErrors = error.response?.data?.errors;

      if (validationErrors) {
        const firstError = Object.values(validationErrors)[0];
        toast({
          title: "Validation Error",
          description: Array.isArray(firstError) ? firstError[0] : firstError,
          variant: "destructive",
        });
      } else {
        toast({
          title: "Error",
          description: errorMsg,
          variant: "destructive",
        });
      }
    } finally {
      setSubmitting(false);
    }
  };

  const generateSlug = (name: string) => {
    return name
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/(^-|-$)/g, "");
  };

  const handleNameChange = (name: string) => {
    setFormData({
      ...formData,
      name,
      slug: generateSlug(name),
    });
  };

  // Bulk Operations Functions
  const toggleSelectAll = () => {
    if (selectedProducts.length === filteredProducts.length) {
      setSelectedProducts([]);
    } else {
      setSelectedProducts(filteredProducts.map((p) => p.id));
    }
  };

  const toggleSelectProduct = (productId: number) => {
    setSelectedProducts((prev) =>
      prev.includes(productId)
        ? prev.filter((id) => id !== productId)
        : [...prev, productId]
    );
  };

  const handleBulkActivate = async () => {
    const count = selectedProducts.length;
    try {
      const promises = selectedProducts.map((id) => {
        const formData = new FormData();
        formData.append('_method', 'PUT');
        formData.append('is_active', '1');
        return updateProduct(id, formData);
      });

      await Promise.all(promises);
      toast({
        title: "Bulk Activate Successful",
        description: `${count} product${count !== 1 ? 's' : ''} activated successfully`,
      });
      setSelectedProducts([]);
      loadData();
    } catch (error: any) {
      toast({
        title: "Error",
        description: `Failed to activate ${count} product${count !== 1 ? 's' : ''}`,
        variant: "destructive",
      });
    }
  };

  const handleBulkDeactivate = async () => {
    const count = selectedProducts.length;
    try {
      const promises = selectedProducts.map((id) => {
        const formData = new FormData();
        formData.append('_method', 'PUT');
        formData.append('is_active', '0');
        return updateProduct(id, formData);
      });

      await Promise.all(promises);
      toast({
        title: "Bulk Deactivate Successful",
        description: `${count} product${count !== 1 ? 's' : ''} deactivated successfully`,
      });
      setSelectedProducts([]);
      loadData();
    } catch (error: any) {
      toast({
        title: "Error",
        description: `Failed to deactivate ${count} product${count !== 1 ? 's' : ''}`,
        variant: "destructive",
      });
    }
  };

  const handleBulkFeature = async () => {
    const count = selectedProducts.length;
    try {
      const promises = selectedProducts.map((id) => {
        const formData = new FormData();
        formData.append('_method', 'PUT');
        formData.append('is_featured', '1');
        return updateProduct(id, formData);
      });

      await Promise.all(promises);
      toast({
        title: "Bulk Feature Successful",
        description: `${count} product${count !== 1 ? 's' : ''} marked as featured`,
      });
      setSelectedProducts([]);
      loadData();
    } catch (error: any) {
      toast({
        title: "Error",
        description: `Failed to feature ${count} product${count !== 1 ? 's' : ''}`,
        variant: "destructive",
      });
    }
  };

  const handleBulkUnfeature = async () => {
    const count = selectedProducts.length;
    try {
      const promises = selectedProducts.map((id) => {
        const formData = new FormData();
        formData.append('_method', 'PUT');
        formData.append('is_featured', '0');
        return updateProduct(id, formData);
      });

      await Promise.all(promises);
      toast({
        title: "Bulk Unfeature Successful",
        description: `${count} product${count !== 1 ? 's' : ''} removed from featured`,
      });
      setSelectedProducts([]);
      loadData();
    } catch (error: any) {
      toast({
        title: "Error",
        description: `Failed to unfeature ${count} product${count !== 1 ? 's' : ''}`,
        variant: "destructive",
      });
    }
  };

  const handleBulkDelete = async () => {
    const count = selectedProducts.length;
    try {
      const promises = selectedProducts.map((id) => deleteProduct(id));
      await Promise.all(promises);

      toast({
        title: "Bulk Delete Successful",
        description: `${count} product${count !== 1 ? 's' : ''} deleted permanently`,
      });
      setSelectedProducts([]);
      setBulkDeleteDialogOpen(false);
      loadData();
    } catch (error: any) {
      toast({
        title: "Error",
        description: `Failed to delete ${count} product${count !== 1 ? 's' : ''}`,
        variant: "destructive",
      });
    }
  };

  const handleExportCSV = () => {
    const productsToExport = selectedProducts.length > 0
      ? filteredProducts.filter((p) => selectedProducts.includes(p.id))
      : filteredProducts;

    if (productsToExport.length === 0) {
      toast({
        title: "No Products",
        description: "No products to export",
        variant: "destructive",
      });
      return;
    }

    // Sort products by ID in ascending order for CSV export
    const sortedProducts = [...productsToExport].sort((a, b) => a.id - b.id);

    // Enhanced CSV headers
    const headers = ["S/N", "Product ID", "Name", "Price (NGN)", "Stock Quantity", "Stock Status", "Status", "Featured", "Slug"];

    // Map products with serial numbers
    const rows = sortedProducts.map((p, index) => [
      index + 1,
      p.id,
      p.name,
      p.price,
      p.stock_quantity,
      p.stock_status.replace('_', ' ').toUpperCase(),
      p.is_active ? "Active" : "Inactive",
      p.is_featured ? "Yes" : "No",
      p.slug,
    ]);

    // Create CSV content with proper escaping
    const csvContent = [
      headers.join(","),
      ...rows.map((row) => row.map((cell) => `"${cell}"`).join(",")),
    ].join("\n");

    // Download CSV file
    const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `products-${new Date().toISOString().split("T")[0]}.csv`;
    link.click();
    URL.revokeObjectURL(url);

    toast({
      title: "Export Successful",
      description: `${productsToExport.length} product${productsToExport.length !== 1 ? 's' : ''} exported to CSV`,
    });

    // Clear selection after export if items were selected
    if (selectedProducts.length > 0) {
      setSelectedProducts([]);
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="h-8 w-8 animate-spin" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold">Products</h1>
          <p className="text-muted-foreground mt-1">
            Manage your product inventory
          </p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" onClick={handleExportCSV}>
            <Download className="mr-2 h-4 w-4" />
            Export All
          </Button>
          <Button onClick={openCreateDialog}>
            <Plus className="mr-2 h-4 w-4" />
            Add Product
          </Button>
        </div>
      </div>

      {/* Bulk Actions Toolbar */}
      {selectedProducts.length > 0 && (
        <div className="bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
          <div className="flex flex-wrap items-center gap-3">
            <div className="flex items-center gap-2">
              <Badge variant="secondary" className="text-sm">
                {selectedProducts.length} selected
              </Badge>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => setSelectedProducts([])}
                className="h-7 text-xs"
              >
                Clear
              </Button>
            </div>

            <div className="flex flex-wrap gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={handleBulkActivate}
                className="h-8"
              >
                <CheckSquare className="mr-1 h-3 w-3" />
                Activate
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={handleBulkDeactivate}
                className="h-8"
              >
                <Square className="mr-1 h-3 w-3" />
                Deactivate
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={handleBulkFeature}
                className="h-8"
              >
                <Star className="mr-1 h-3 w-3" />
                Feature
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={handleBulkUnfeature}
                className="h-8"
              >
                <Star className="mr-1 h-3 w-3 fill-gray-300 dark:fill-gray-600" />
                Unfeature
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={handleExportCSV}
                className="h-8"
              >
                <Download className="mr-1 h-3 w-3" />
                Export CSV
              </Button>
              <Button
                variant="destructive"
                size="sm"
                onClick={() => setBulkDeleteDialogOpen(true)}
                className="h-8"
              >
                <Trash2 className="mr-1 h-3 w-3" />
                Delete
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* Search and Filter */}
      <div className="flex flex-col sm:flex-row gap-4">
        {/* Search */}
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Search products by name..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="pl-10"
          />
        </div>

        {/* Featured Filter */}
        <div className="w-full sm:w-[180px]">
          <Select
            value={featuredFilter}
            onValueChange={setFeaturedFilter}
          >
            <SelectTrigger>
              <Star className="mr-2 h-4 w-4" />
              <SelectValue placeholder="All Products" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Products</SelectItem>
              <SelectItem value="featured">Featured Only</SelectItem>
              <SelectItem value="regular">Regular Only</SelectItem>
            </SelectContent>
          </Select>
        </div>

        {/* Sort Options */}
        <div className="w-full sm:w-[200px]">
          <Select
            value={sortBy}
            onValueChange={setSortBy}
          >
            <SelectTrigger>
              <ArrowUpDown className="mr-2 h-4 w-4" />
              <SelectValue placeholder="Sort By" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="newest">Newest First</SelectItem>
              <SelectItem value="oldest">Oldest First</SelectItem>
              <SelectItem value="price-low">Price: Low to High</SelectItem>
              <SelectItem value="price-high">Price: High to Low</SelectItem>
              <SelectItem value="name-asc">Name: A to Z</SelectItem>
              <SelectItem value="name-desc">Name: Z to A</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      <div className="border rounded-lg">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-12">
                <Checkbox
                  checked={selectedProducts.length === filteredProducts.length && filteredProducts.length > 0}
                  onCheckedChange={toggleSelectAll}
                  aria-label="Select all"
                  className={
                    selectedProducts.length > 0 && selectedProducts.length < filteredProducts.length
                      ? "data-[state=checked]:bg-blue-500 dark:data-[state=checked]:bg-blue-600"
                      : ""
                  }
                  {...(selectedProducts.length > 0 && selectedProducts.length < filteredProducts.length
                    ? { "data-indeterminate": "true" }
                    : {})}
                />
              </TableHead>
              <TableHead>Image</TableHead>
              <TableHead>Name</TableHead>
              <TableHead>Price</TableHead>
              <TableHead>Stock</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Featured</TableHead>
              <TableHead className="text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {loading ? (
              <TableRow>
                <TableCell colSpan={8} className="text-center py-8">
                  <Loader2 className="h-6 w-6 animate-spin mx-auto" />
                </TableCell>
              </TableRow>
            ) : filteredProducts.length === 0 ? (
              <TableRow>
                <TableCell colSpan={8} className="text-center py-8 text-muted-foreground">
                  {searchQuery
                    ? "No products match your search criteria."
                    : "No products found. Click \"Add Product\" to create one."}
                </TableCell>
              </TableRow>
            ) : (
              filteredProducts.map((product) => (
                <TableRow key={product.id}>
                  <TableCell>
                    <Checkbox
                      checked={selectedProducts.includes(product.id)}
                      onCheckedChange={() => toggleSelectProduct(product.id)}
                      aria-label={`Select ${product.name}`}
                    />
                  </TableCell>
                  <TableCell>
                    {product.image_full_url ? (
                      <img
                        src={product.image_full_url}
                        alt={product.name}
                        className="h-12 w-12 object-cover rounded"
                      />
                    ) : (
                      <div className="h-12 w-12 bg-muted rounded flex items-center justify-center text-muted-foreground text-xs">
                        No image
                      </div>
                    )}
                  </TableCell>
                  <TableCell className="font-medium">{product.name}</TableCell>
                  <TableCell>&#8358;{product.price.toLocaleString()}</TableCell>
                  <TableCell>
                    <div className="flex flex-col gap-1">
                      <StockBadge
                        stockStatus={product.stock_status}
                        stockQuantity={product.stock_quantity}
                        showQuantity
                      />
                    </div>
                  </TableCell>
                  <TableCell>
                    <Badge variant={product.is_active ? "default" : "secondary"}>
                      {product.is_active ? "Active" : "Inactive"}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => handleToggleFeatured(product)}
                      className="h-8 w-8 p-0"
                    >
                      <Star
                        className={`h-4 w-4 ${
                          product.is_featured
                            ? "fill-yellow-400 text-yellow-400 dark:fill-yellow-500 dark:text-yellow-500"
                            : "text-muted-foreground"
                        }`}
                      />
                    </Button>
                  </TableCell>
                  <TableCell className="text-right">
                    <div className="flex justify-end gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => {
                          setSelectedProductForStock(product);
                          setStockModalOpen(true);
                        }}
                        title="Manage Stock"
                      >
                        <Package className="h-4 w-4" />
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => {
                          setSelectedProductForStock(product);
                          setStockHistoryModalOpen(true);
                        }}
                        title="Stock History"
                      >
                        <History className="h-4 w-4" />
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => openEditDialog(product)}
                      >
                        <Pencil className="h-4 w-4" />
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => {
                          setProductToDelete(product);
                          setDeleteDialogOpen(true);
                        }}
                      >
                        <Trash2 className="h-4 w-4 text-red-500 dark:text-red-400" />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      {/* Delete Confirmation Dialog */}
      <AlertDialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Are you sure?</AlertDialogTitle>
            <AlertDialogDescription>
              This will permanently delete "{productToDelete?.name}". This action
              cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={handleDelete} className="bg-red-600 hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-600">
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Bulk Delete Confirmation Dialog */}
      <AlertDialog open={bulkDeleteDialogOpen} onOpenChange={setBulkDeleteDialogOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete {selectedProducts.length} products?</AlertDialogTitle>
            <AlertDialogDescription>
              This will permanently delete {selectedProducts.length} selected product{selectedProducts.length > 1 ? 's' : ''}.
              This action cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={handleBulkDelete} className="bg-red-600 hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-600">
              Delete {selectedProducts.length} Product{selectedProducts.length > 1 ? 's' : ''}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Create/Edit Product Dialog */}
      <Dialog open={formDialogOpen} onOpenChange={setFormDialogOpen}>
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>
              {editingProduct ? "Edit Product" : "Create New Product"}
            </DialogTitle>
            <DialogDescription>
              {editingProduct
                ? "Update the product details below"
                : "Fill in the details to create a new product"}
            </DialogDescription>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <Label htmlFor="name">Product Name *</Label>
              <Input
                id="name"
                value={formData.name}
                onChange={(e) => handleNameChange(e.target.value)}
                required
                placeholder="e.g., Golden Penny Semovita 2kg"
              />
            </div>

            <div>
              <Label htmlFor="slug">Slug (auto-generated)</Label>
              <Input
                id="slug"
                value={formData.slug}
                onChange={(e) => setFormData({ ...formData, slug: e.target.value })}
                required
                placeholder="golden-penny-semovita-2kg"
              />
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label htmlFor="sku">SKU (optional)</Label>
                <Input
                  id="sku"
                  value={formData.sku}
                  onChange={(e) => setFormData({ ...formData, sku: e.target.value })}
                  placeholder="SKU-001"
                />
              </div>

              <div>
                <Label>Barcodes (optional)</Label>
                <div className="space-y-2">
                  {formData.barcodes.map((barcode, index) => (
                    <div key={index} className="flex gap-2">
                      <Input
                        value={barcode}
                        onChange={(e) => {
                          const newBarcodes = [...formData.barcodes];
                          newBarcodes[index] = e.target.value;
                          setFormData({ ...formData, barcodes: newBarcodes });
                        }}
                        placeholder="Scan or enter barcode"
                      />
                      <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        onClick={() => {
                          const newBarcodes = formData.barcodes.filter((_, i) => i !== index);
                          setFormData({ ...formData, barcodes: newBarcodes });
                        }}
                        title="Remove barcode"
                      >
                        <X className="h-4 w-4" />
                      </Button>
                    </div>
                  ))}
                  <div className="flex gap-2">
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => setFormData({ ...formData, barcodes: [...formData.barcodes, ""] })}
                    >
                      <Plus className="h-4 w-4 mr-1" />
                      Add Barcode
                    </Button>
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => setShowBarcodeScanner(true)}
                      title="Scan barcode"
                    >
                      <Scan className="h-4 w-4 mr-1" />
                      Scan
                    </Button>
                  </div>
                </div>
              </div>
            </div>

            <div>
              <Label htmlFor="price">Price (NGN) *</Label>
              <Input
                id="price"
                type="number"
                value={formData.price}
                onChange={(e) => setFormData({ ...formData, price: e.target.value })}
                required
                placeholder="8500"
              />
            </div>

            <div>
              <Label htmlFor="description">Description</Label>
              <Textarea
                id="description"
                value={formData.description}
                onChange={(e) =>
                  setFormData({ ...formData, description: e.target.value })
                }
                placeholder="Brief description of the product"
                rows={3}
              />
            </div>

            <div>
              <Label htmlFor="image">Product Image</Label>
              <Input
                id="image"
                type="file"
                accept="image/*"
                onChange={(e) => setImageFile(e.target.files?.[0] || null)}
              />
              <p className="text-sm text-muted-foreground mt-1">
                Maximum file size: 2MB
              </p>
              {editingProduct?.image_full_url && !imageFile && (
                <img
                  src={editingProduct.image_full_url}
                  alt="Current"
                  className="mt-2 h-24 w-24 object-cover rounded border"
                />
              )}
            </div>

            <div className="border rounded-lg p-4 bg-blue-50/50 dark:bg-blue-950/20 space-y-4">
              <div className="flex items-center gap-2 mb-3">
                <Package className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                <h3 className="font-semibold text-sm">Batch & Expiry Tracking</h3>
                <AlertTriangle className="h-4 w-4 text-orange-500 dark:text-orange-400 ml-auto" title="Enable for products that require batch/expiry tracking" />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="flex items-center justify-between p-3 border rounded-lg bg-card">
                  <div className="flex items-center gap-2">
                    <Package className="h-4 w-4 text-muted-foreground" />
                    <div>
                      <Label htmlFor="track_batch" className="cursor-pointer font-medium">
                        Track Batches
                      </Label>
                      <p className="text-xs text-muted-foreground">
                        Enable batch number tracking
                      </p>
                    </div>
                  </div>
                  <Switch
                    id="track_batch"
                    checked={formData.track_batch}
                    onCheckedChange={(checked) =>
                      setFormData({ ...formData, track_batch: checked })
                    }
                  />
                </div>

                <div className="flex items-center justify-between p-3 border rounded-lg bg-card">
                  <div className="flex items-center gap-2">
                    <Calendar className="h-4 w-4 text-orange-500 dark:text-orange-400" />
                    <div>
                      <Label htmlFor="track_expiry" className="cursor-pointer font-medium">
                        Track Expiry
                      </Label>
                      <p className="text-xs text-muted-foreground">
                        Enable expiry date tracking
                      </p>
                    </div>
                  </div>
                  <Switch
                    id="track_expiry"
                    checked={formData.track_expiry}
                    onCheckedChange={(checked) =>
                      setFormData({ ...formData, track_expiry: checked })
                    }
                  />
                </div>
              </div>


              {/* Show batch/expiry fields when editing if product has existing data */}
              {editingProduct && (formData.track_batch || formData.track_expiry || formData.batch_number || formData.expiry_date) && (
                <div className="space-y-3 pt-3 border-t">
                  <p className="text-xs font-semibold text-muted-foreground uppercase">Current Batch Information (if any)</p>

                  <div className="grid grid-cols-3 gap-3">
                    {(formData.track_batch || formData.batch_number) && (
                      <div>
                        <Label htmlFor="batch_number" className="text-xs">Batch Number</Label>
                        <Input
                          id="batch_number"
                          value={formData.batch_number}
                          onChange={(e) => setFormData({ ...formData, batch_number: e.target.value })}
                          placeholder="Optional"
                          className="h-9 text-sm"
                        />
                      </div>
                    )}

                    {formData.manufacturing_date && (
                      <div>
                        <Label htmlFor="manufacturing_date" className="text-xs">Mfg. Date</Label>
                        <MonthYearPicker
                          value={formData.manufacturing_date}
                          onChange={(v) => setFormData({ ...formData, manufacturing_date: v })}
                          placeholder="Optional"
                          maxDate={new Date()}
                        />
                      </div>
                    )}

                    {(formData.track_expiry || formData.expiry_date) && (
                      <div>
                        <Label htmlFor="expiry_date" className="text-xs">Expiry Date</Label>
                        <MonthYearPicker
                          value={formData.expiry_date}
                          onChange={(v) => setFormData({ ...formData, expiry_date: v })}
                          placeholder="Optional"
                          minDate={new Date()}
                        />
                      </div>
                    )}
                  </div>

                </div>
              )}
            </div>

            <div className="flex items-center gap-2">
              <input
                type="checkbox"
                id="is_active"
                checked={formData.is_active}
                onChange={(e) =>
                  setFormData({ ...formData, is_active: e.target.checked })
                }
                className="h-4 w-4"
              />
              <Label htmlFor="is_active" className="cursor-pointer">
                Active
              </Label>
            </div>

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setFormDialogOpen(false)}
                disabled={submitting}
              >
                Cancel
              </Button>
              <Button type="submit" disabled={submitting}>
                {submitting ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    Saving...
                  </>
                ) : editingProduct ? (
                  "Update Product"
                ) : (
                  "Create Product"
                )}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Stock Adjustment Modal */}
      <StockAdjustmentModal
        open={stockModalOpen}
        onClose={() => {
          setStockModalOpen(false);
          setSelectedProductForStock(null);
        }}
        product={selectedProductForStock}
        onSuccess={() => {
          loadData();
        }}
      />

      {/* Stock History Modal */}
      <StockHistoryModal
        open={stockHistoryModalOpen}
        onClose={() => {
          setStockHistoryModalOpen(false);
          setSelectedProductForStock(null);
        }}
        productId={selectedProductForStock?.id || null}
        productName={selectedProductForStock?.name || ""}
      />

      {/* Barcode Scanner Dialog */}
      <Dialog open={showBarcodeScanner} onOpenChange={setShowBarcodeScanner}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>Scan Product Barcode</DialogTitle>
          </DialogHeader>
          <BarcodeScanner
            onScan={(barcode) => {
              setFormData({ ...formData, barcodes: [...formData.barcodes, barcode] });
              setShowBarcodeScanner(false);
              toast({
                title: "Barcode Scanned",
                description: `Barcode: ${barcode}`,
              });
            }}
            onClose={() => setShowBarcodeScanner(false)}
            title="Scan barcode for product"
          />
        </DialogContent>
      </Dialog>
    </div>
  );
}