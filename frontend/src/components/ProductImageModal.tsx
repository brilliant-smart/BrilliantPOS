import { Dialog, DialogContent } from "@/components/ui/dialog";
import { X } from "lucide-react";
import { Button } from "@/components/ui/button";

interface ProductImageModalProps {
  isOpen: boolean;
  onClose: () => void;
  image: string;
  productName: string;
}

export function ProductImageModal({ isOpen, onClose, image, productName }: ProductImageModalProps) {
  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-3xl p-0 overflow-hidden bg-background border-border">
        <div className="relative">
          <Button
            variant="ghost"
            size="icon"
            className="absolute top-3 right-3 z-10 bg-background/80 hover:bg-background rounded-full"
            onClick={onClose}
          >
            <X className="h-5 w-5" />
          </Button>
          <img
            src={image}
            alt={productName}
            className="w-full h-auto max-h-[80vh] object-contain"
          />
        </div>
        <div className="p-4 border-t border-border">
          <h3 className="font-display text-lg font-semibold text-foreground">{productName}</h3>
        </div>
      </DialogContent>
    </Dialog>
  );
}
