import { Injectable, signal } from '@angular/core';

export interface Toast {
  id: number;
  type: 'success' | 'error' | 'warning' | 'info';
  title: string;
  message: string;
  duration: number;
  createdAt: Date;
}

@Injectable({
  providedIn: 'root'
})
export class ToastService {
  private toastsSignal = signal<Toast[]>([]);
  private idCounter = 0;

  readonly toasts = this.toastsSignal.asReadonly();

  show(type: Toast['type'], title: string, message: string, duration = 5000): void {
    const toast: Toast = {
      id: ++this.idCounter,
      type,
      title,
      message,
      duration,
      createdAt: new Date()
    };

    this.toastsSignal.update(toasts => [...toasts, toast]);

    if (duration > 0) {
      setTimeout(() => this.dismiss(toast.id), duration);
    }
  }

  success(title: string, message: string, duration = 5000): void {
    this.show('success', title, message, duration);
  }

  error(title: string, message: string, duration = 7000): void {
    this.show('error', title, message, duration);
  }

  warning(title: string, message: string, duration = 6000): void {
    this.show('warning', title, message, duration);
  }

  info(title: string, message: string, duration = 5000): void {
    this.show('info', title, message, duration);
  }

  dismiss(id: number): void {
    this.toastsSignal.update(toasts => toasts.filter(t => t.id !== id));
  }

  clear(): void {
    this.toastsSignal.set([]);
  }
}
