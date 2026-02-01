import { Injectable, signal, computed } from '@angular/core';
import { Notification } from '../models';

@Injectable({
  providedIn: 'root'
})
export class NotificationService {
  private notificationsSignal = signal<Notification[]>([]);

  readonly notifications = this.notificationsSignal.asReadonly();
  readonly unreadCount = computed(() =>
    this.notificationsSignal().filter(n => !n.read).length
  );

  constructor() {
    this.loadFromStorage();
  }

  private loadFromStorage(): void {
    const stored = localStorage.getItem('notifications');
    if (stored) {
      try {
        const parsed = JSON.parse(stored);
        this.notificationsSignal.set(parsed.map((n: Notification) => ({
          ...n,
          createdAt: new Date(n.createdAt)
        })));
      } catch {
        this.notificationsSignal.set([]);
      }
    }
  }

  private saveToStorage(): void {
    localStorage.setItem('notifications', JSON.stringify(this.notificationsSignal()));
  }

  add(notification: Omit<Notification, 'id' | 'read' | 'createdAt'>): void {
    const newNotification: Notification = {
      ...notification,
      id: crypto.randomUUID(),
      read: false,
      createdAt: new Date()
    };

    this.notificationsSignal.update(notifications => [newNotification, ...notifications]);
    this.saveToStorage();
  }

  markAsRead(id: string): void {
    this.notificationsSignal.update(notifications =>
      notifications.map(n => n.id === id ? { ...n, read: true } : n)
    );
    this.saveToStorage();
  }

  markAllAsRead(): void {
    this.notificationsSignal.update(notifications =>
      notifications.map(n => ({ ...n, read: true }))
    );
    this.saveToStorage();
  }

  remove(id: string): void {
    this.notificationsSignal.update(notifications =>
      notifications.filter(n => n.id !== id)
    );
    this.saveToStorage();
  }

  clear(): void {
    this.notificationsSignal.set([]);
    this.saveToStorage();
  }
}
