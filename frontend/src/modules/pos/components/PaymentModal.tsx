import POSPaymentDrawer from '@/pages/admin/pos/POSPaymentDrawer';
import { UsePosCartReturn, UsePaymentReturn, UseScannerReturn } from '../composables';

interface PaymentModalProps {
  open: boolean;
  onClose: () => void;
  cart: UsePosCartReturn;
  payment: UsePaymentReturn;
  scanner: UseScannerReturn;
  customerName?: string;
}

export default function PaymentModal({
  open,
  onClose,
  cart,
  payment,
  scanner,
  customerName,
}: PaymentModalProps) {
  return (
    <POSPaymentDrawer
      open={open}
      onClose={() => {
        onClose();
        scanner.focusScanner();
      }}
      cart={cart}
      payment={payment}
      customerName={customerName}
    />
  );
}
