export interface Payment {
  method: string;
  amount: number;
}

export function calcTotalPaid(payments: Payment[]): number {
  return payments.reduce((sum, p) => sum + p.amount, 0);
}

export function calcBalance(totalAmount: number, totalPaid: number): number {
  return Math.max(0, totalAmount - totalPaid);
}

export function calcChange(totalAmount: number, totalPaid: number): number {
  return Math.max(0, totalPaid - totalAmount);
}

export function canCompleteSale(
  totalAmount: number,
  totalPaid: number,
  hasCreditPayment: boolean
): boolean {
  return hasCreditPayment || totalPaid >= totalAmount;
}