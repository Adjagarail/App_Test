import { Injectable, inject, signal } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, tap, catchError } from 'rxjs';
import { Notification } from '../models';
import { NotificationsResponse, NotificationResponse } from '../models';
import { environment } from '../../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class NotificationService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;

  private notificationsSignal = signal<Notification[]>([]);
  private unreadCountSignal = signal<number>(0);
  private loadingSignal = signal<boolean>(false);

  readonly notifications = this.notificationsSignal.asReadonly();
  readonly unreadCount = this.unreadCountSignal.asReadonly();
  readonly loading = this.loadingSignal.asReadonly();

  loadNotifications(page = 1, limit = 20): Observable<NotificationsResponse> {
    this.loadingSignal.set(true);

    const params = new HttpParams()
      .set('page', page.toString())
      .set('limit', limit.toString());

    return this.http.get<NotificationsResponse>(`${this.apiUrl}/me/notifications`, { params }).pipe(
      tap(response => {
        const notifications = response.notifications.map(n => this.mapToNotification(n));
        if (page === 1) {
          this.notificationsSignal.set(notifications);
        } else {
          this.notificationsSignal.update(existing => [...existing, ...notifications]);
        }
        this.unreadCountSignal.set(response.unreadCount);
        this.loadingSignal.set(false);
      }),
      catchError(error => {
        this.loadingSignal.set(false);
        throw error;
      })
    );
  }

  markAsRead(id: number): Observable<void> {
    return this.http.patch<void>(`${this.apiUrl}/me/notifications/${id}`, {}).pipe(
      tap(() => {
        this.notificationsSignal.update(notifications =>
          notifications.map(n => n.id === id ? { ...n, read: true } : n)
        );
        this.unreadCountSignal.update(count => Math.max(0, count - 1));
      })
    );
  }

  markAllAsRead(): Observable<void> {
    return this.http.patch<void>(`${this.apiUrl}/me/notifications/mark-all-read`, {}).pipe(
      tap(() => {
        this.notificationsSignal.update(notifications =>
          notifications.map(n => ({ ...n, read: true }))
        );
        this.unreadCountSignal.set(0);
      })
    );
  }

  getUnreadCount(): Observable<{ count: number }> {
    return this.http.get<{ count: number }>(`${this.apiUrl}/me/notifications/unread-count`).pipe(
      tap(response => this.unreadCountSignal.set(response.count))
    );
  }

  addLocalNotification(notification: Omit<Notification, 'id' | 'read' | 'createdAt'>): void {
    const newNotification: Notification = {
      ...notification,
      id: Date.now(),
      read: false,
      createdAt: new Date().toISOString()
    };

    this.notificationsSignal.update(notifications => [newNotification, ...notifications]);
    this.unreadCountSignal.update(count => count + 1);
  }

  clear(): void {
    this.notificationsSignal.set([]);
    this.unreadCountSignal.set(0);
  }

  private mapToNotification(response: NotificationResponse): Notification {
    return {
      id: response.id,
      type: response.type,
      title: response.payload.title,
      message: response.payload.message,
      read: response.isRead,
      createdAt: response.createdAt,
      readAt: response.readAt || undefined,
      link: response.payload.link,
      payload: response.payload
    };
  }
}
