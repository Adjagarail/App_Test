import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ToastService, Toast } from '../../../core/services/toast.service';

@Component({
  selector: 'app-toast',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="toast-container">
      @for (toast of toastService.toasts(); track toast.id) {
        <div class="toast" [class]="'toast-' + toast.type" (click)="dismiss(toast.id)">
          <div class="toast-icon">
            @switch (toast.type) {
              @case ('success') {
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              }
              @case ('error') {
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              }
              @case ('warning') {
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
              }
              @default {
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              }
            }
          </div>
          <div class="toast-content">
            <p class="toast-title">{{ toast.title }}</p>
            <p class="toast-message">{{ toast.message }}</p>
          </div>
          <button class="toast-close" (click)="dismiss(toast.id); $event.stopPropagation()">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
          <div class="toast-progress" [style.animation-duration.ms]="toast.duration"></div>
        </div>
      }
    </div>
  `,
  styles: [`
    .toast-container {
      position: fixed;
      top: 80px;
      right: 20px;
      z-index: 10000;
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
      max-width: 400px;
      width: 100%;
      pointer-events: none;
    }

    .toast {
      display: flex;
      align-items: flex-start;
      gap: 0.75rem;
      padding: 1rem;
      border-radius: 12px;
      background: white;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
      animation: slideIn 0.3s ease-out;
      cursor: pointer;
      pointer-events: all;
      position: relative;
      overflow: hidden;
      border-left: 4px solid;
    }

    @keyframes slideIn {
      from {
        transform: translateX(100%);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }

    .toast-success {
      border-left-color: #10b981;
      background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    }

    .toast-error {
      border-left-color: #ef4444;
      background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    }

    .toast-warning {
      border-left-color: #f59e0b;
      background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
    }

    .toast-info {
      border-left-color: #3b82f6;
      background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    }

    .toast-icon {
      flex-shrink: 0;
      width: 24px;
      height: 24px;

      svg {
        width: 100%;
        height: 100%;
      }
    }

    .toast-success .toast-icon { color: #059669; }
    .toast-error .toast-icon { color: #dc2626; }
    .toast-warning .toast-icon { color: #d97706; }
    .toast-info .toast-icon { color: #2563eb; }

    .toast-content {
      flex: 1;
      min-width: 0;
    }

    .toast-title {
      margin: 0 0 0.25rem 0;
      font-weight: 600;
      font-size: 0.9rem;
      color: #1f2937;
    }

    .toast-message {
      margin: 0;
      font-size: 0.85rem;
      color: #4b5563;
      line-height: 1.4;
    }

    .toast-close {
      flex-shrink: 0;
      padding: 0.25rem;
      background: none;
      border: none;
      cursor: pointer;
      color: #9ca3af;
      border-radius: 6px;
      transition: all 0.2s;

      &:hover {
        background: rgba(0, 0, 0, 0.1);
        color: #4b5563;
      }

      svg {
        width: 16px;
        height: 16px;
      }
    }

    .toast-progress {
      position: absolute;
      bottom: 0;
      left: 0;
      height: 3px;
      width: 100%;
      animation: progress linear forwards;
    }

    @keyframes progress {
      from { width: 100%; }
      to { width: 0%; }
    }

    .toast-success .toast-progress { background: #10b981; }
    .toast-error .toast-progress { background: #ef4444; }
    .toast-warning .toast-progress { background: #f59e0b; }
    .toast-info .toast-progress { background: #3b82f6; }

    @media (max-width: 768px) {
      .toast-container {
        left: 10px;
        right: 10px;
        max-width: none;
      }
    }
  `]
})
export class ToastComponent {
  protected readonly toastService = inject(ToastService);

  dismiss(id: number): void {
    this.toastService.dismiss(id);
  }
}
