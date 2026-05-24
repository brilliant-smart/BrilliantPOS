import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Plus, Search, Edit, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { DataPagination } from '@/components/DataPagination';
import { supplierApi } from '@/app/api/suppliers';
import { Supplier } from '@/types/ims';
import { toast } from 'sonner';
import { useAuth } from '@/app/auth/AuthContext';

const PER_PAGE = 20;

export default function SupplierList() {
  const navigate = useNavigate();
  const { user } = useAuth();
  const [suppliers, setSuppliers] = useState<Supplier[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalRecords, setTotalRecords] = useState(0);
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [supplierToDelete, setSupplierToDelete] = useState<Supplier | null>(null);

  const isOwnerOrManager = user?.role === 'owner' || user?.role === 'manager';

  const loadSuppliers = async (page: number = 1) => {
    try {
      setLoading(true);
      const result = await supplierApi.getAll({
        page,
        per_page: PER_PAGE,
        search: search || undefined,
      });
      setSuppliers(result.data);
      setCurrentPage(result.current_page);
      setTotalPages(result.last_page);
      setTotalRecords(result.total);
    } catch (error: any) {
      toast.error(error.message || 'Failed to load suppliers');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadSuppliers(1);
  }, []);

  const handleSearch = (value: string) => {
    setSearch(value);
    setCurrentPage(1);
    loadSuppliersWithSearch(value, 1);
  };

  const loadSuppliersWithSearch = async (searchTerm: string, page: number) => {
    try {
      setLoading(true);
      const result = await supplierApi.getAll({
        page,
        per_page: PER_PAGE,
        search: searchTerm || undefined,
      });
      setSuppliers(result.data);
      setCurrentPage(result.current_page);
      setTotalPages(result.last_page);
      setTotalRecords(result.total);
    } catch (error: any) {
      toast.error(error.message || 'Failed to load suppliers');
    } finally {
      setLoading(false);
    }
  };

  const handleDeleteClick = (supplier: Supplier) => {
    setSupplierToDelete(supplier);
    setDeleteDialogOpen(true);
  };

  const handleDeleteConfirm = async () => {
    if (!supplierToDelete) return;

    try {
      await supplierApi.delete(supplierToDelete.id);
      toast.success('Supplier deleted successfully');
      loadSuppliers(currentPage);
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Failed to delete supplier');
    } finally {
      setDeleteDialogOpen(false);
      setSupplierToDelete(null);
    }
  };

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold">Suppliers</h1>
          <p className="text-muted-foreground">Manage your suppliers and vendor relationships</p>
        </div>
        {isOwnerOrManager && (
          <Link to="/admin/suppliers/create">
            <Button>
              <Plus className="mr-2 h-4 w-4" />
              Add Supplier
            </Button>
          </Link>
        )}
      </div>

      <div className="flex items-center space-x-2">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            placeholder="Search suppliers..."
            value={search}
            onChange={(e) => handleSearch(e.target.value)}
            className="pl-9"
          />
        </div>
      </div>

      {loading ? (
        <Card>
          <CardContent className="p-4 md:p-6">
            <p className="text-center text-muted-foreground">Loading suppliers...</p>
          </CardContent>
        </Card>
      ) : suppliers.length === 0 ? (
        <Card>
          <CardContent className="p-4 md:p-6">
            <p className="text-center text-muted-foreground">
              {search ? 'No suppliers found matching your search' : 'No suppliers yet. Create your first one!'}
            </p>
          </CardContent>
        </Card>
      ) : (
        <>
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {suppliers.map((supplier) => (
              <Card key={supplier.id} className="hover:shadow-md transition-shadow">
                <CardHeader>
                  <div className="flex items-start justify-between">
                    <div>
                      <CardTitle className="text-lg">{supplier.name}</CardTitle>
                      <p className="text-sm text-muted-foreground">{supplier.code}</p>
                    </div>
                    <Badge variant={supplier.is_active ? 'default' : 'secondary'}>
                      {supplier.is_active ? 'Active' : 'Inactive'}
                    </Badge>
                  </div>
                </CardHeader>
                <CardContent className="space-y-2">
                  {supplier.contact_person && (
                    <p className="text-sm">
                      <span className="font-medium">Contact:</span> {supplier.contact_person}
                    </p>
                  )}
                  {supplier.email && (
                    <p className="text-sm">
                      <span className="font-medium">Email:</span> {supplier.email}
                    </p>
                  )}
                  {supplier.phone && (
                    <p className="text-sm">
                      <span className="font-medium">Phone:</span> {supplier.phone}
                    </p>
                  )}
                  <p className="text-sm">
                    <span className="font-medium">Payment Terms:</span>{' '}
                    {supplier.payment_terms.replace('_', ' ').toUpperCase()}
                  </p>

                  {isOwnerOrManager && (
                    <div className="flex gap-2 mt-4 pt-4 border-t">
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => navigate(`/admin/suppliers/${supplier.id}/edit`)}
                        className="flex-1"
                      >
                        <Edit className="h-4 w-4 mr-2" />
                        Edit
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handleDeleteClick(supplier)}
                        className="flex-1 text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30"
                      >
                        <Trash2 className="h-4 w-4 mr-2" />
                        Delete
                      </Button>
                    </div>
                  )}
                </CardContent>
              </Card>
            ))}
          </div>
          <DataPagination
            currentPage={currentPage}
            totalPages={totalPages}
            totalRecords={totalRecords}
            perPage={PER_PAGE}
            onPageChange={(page) => loadSuppliers(page)}
          />
        </>
      )}

      {/* Delete Confirmation Dialog */}
      <AlertDialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete Supplier</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to delete <strong>{supplierToDelete?.name}</strong>?
              This action cannot be undone and may affect related purchase orders.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleDeleteConfirm}
              className="bg-red-600 hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-600"
            >
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}