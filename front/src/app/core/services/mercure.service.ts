import { Injectable, signal, OnDestroy } from '@angular/core';
import { environment } from '../../../environments/environment';

export interface MercureMessage {
  type: string;
  threadId?: number;
  messageId?: number;
  sender?: {
    id: number;
    email: string;
    nomComplet: string | null;
  };
  admin?: {
    id: number;
    email: string;
    nomComplet: string | null;
  };
  message?: string;
  timestamp?: string;
  [key: string]: unknown;
}

interface ReconnectInfo {
  topic: string;
  callback: (data: MercureMessage) => void;
  attempts: number;
}

@Injectable({
  providedIn: 'root'
})
export class MercureService implements OnDestroy {
  private eventSources: Map<string, EventSource> = new Map();
  private reconnectTimers: Map<string, ReturnType<typeof setTimeout>> = new Map();
  private callbacks: Map<string, (data: MercureMessage) => void> = new Map();

  // Active users count signal
  activeUsersCount = signal(0);

  // Unread reports count for admins
  unreadReportsCount = signal(0);

  // Unread reports count for regular users
  userUnreadReportsCount = signal(0);

  // Connection status
  isConnected = signal(false);

  private readonly mercureUrl = environment.mercureUrl || 'http://localhost:3000/.well-known/mercure';
  private readonly maxReconnectAttempts = 10;
  private readonly baseReconnectDelay = 1000;

  ngOnDestroy(): void {
    this.closeAll();
  }

  /**
   * Subscribe to a Mercure topic with automatic reconnection.
   */
  subscribe(topic: string, callback: (data: MercureMessage) => void): EventSource | null {
    // If already subscribed, just update the callback
    if (this.eventSources.has(topic)) {
      this.callbacks.set(topic, callback);
      return this.eventSources.get(topic) || null;
    }

    this.callbacks.set(topic, callback);
    return this.createEventSource(topic, 0);
  }

  private createEventSource(topic: string, attempts: number): EventSource | null {
    const url = new URL(this.mercureUrl);
    url.searchParams.append('topic', topic);

    try {
      const eventSource = new EventSource(url.toString());

      eventSource.onopen = () => {
        this.isConnected.set(true);
        console.log(`Mercure connected to topic: ${topic}`);
      };

      eventSource.onmessage = (event) => {
        try {
          const data = JSON.parse(event.data) as MercureMessage;
          const callback = this.callbacks.get(topic);
          if (callback) {
            callback(data);
          }
        } catch {
          console.warn('Failed to parse Mercure message:', event.data);
        }
      };

      eventSource.onerror = () => {
        console.warn(`Mercure connection error for topic: ${topic}`);
        this.isConnected.set(false);

        // Close the broken connection
        eventSource.close();
        this.eventSources.delete(topic);

        // Reconnect with exponential backoff
        if (attempts < this.maxReconnectAttempts) {
          const delay = this.baseReconnectDelay * Math.pow(2, attempts);
          console.log(`Reconnecting to ${topic} in ${delay}ms (attempt ${attempts + 1})`);

          const timer = setTimeout(() => {
            this.createEventSource(topic, attempts + 1);
          }, delay);

          this.reconnectTimers.set(topic, timer);
        } else {
          console.error(`Max reconnect attempts reached for topic: ${topic}`);
        }
      };

      this.eventSources.set(topic, eventSource);
      return eventSource;
    } catch (error) {
      console.warn('Failed to create EventSource for Mercure:', error);
      return null;
    }
  }

  /**
   * Unsubscribe from a Mercure topic.
   */
  unsubscribe(topic: string): void {
    // Clear any pending reconnect timer
    const timer = this.reconnectTimers.get(topic);
    if (timer) {
      clearTimeout(timer);
      this.reconnectTimers.delete(topic);
    }

    const eventSource = this.eventSources.get(topic);
    if (eventSource) {
      eventSource.close();
      this.eventSources.delete(topic);
    }

    this.callbacks.delete(topic);
  }

  /**
   * Close all connections.
   */
  closeAll(): void {
    // Clear all reconnect timers
    this.reconnectTimers.forEach(timer => clearTimeout(timer));
    this.reconnectTimers.clear();

    // Close all event sources
    this.eventSources.forEach(es => es.close());
    this.eventSources.clear();

    this.callbacks.clear();
    this.isConnected.set(false);
  }

  /**
   * Subscribe to dashboard active users updates.
   */
  subscribeToActiveUsers(): void {
    this.subscribe('dashboard/active-users', (data) => {
      if (data.type === 'active_users_update' && typeof data['count'] === 'number') {
        this.activeUsersCount.set(data['count'] as number);
      }
    });
  }

  /**
   * Subscribe to admin reports (new messages from users).
   * For ROLE_ADMIN users.
   */
  subscribeToAdminReports(callback: (data: MercureMessage) => void): void {
    this.subscribe('admin/reports', (data) => {
      if (data.type === 'new_report_message') {
        callback(data);
        this.unreadReportsCount.update(c => c + 1);
      }
    });
  }

  /**
   * Subscribe to admin notifications.
   */
  subscribeToAdminNotifications(callback: (data: MercureMessage) => void): void {
    this.subscribe('admin/notifications', (data) => {
      if (data.type === 'admin_notification') {
        callback(data);
      }
    });
  }

  /**
   * Subscribe to user's report responses.
   * For regular users to receive real-time notifications when admin replies.
   */
  subscribeToMyReports(userId: number, callback: (data: MercureMessage) => void): void {
    const topic = `user/${userId}/reports`;
    this.subscribe(topic, (data) => {
      if (data.type === 'report_response') {
        callback(data);
        this.userUnreadReportsCount.update(c => c + 1);
      }
    });
  }

  /**
   * Unsubscribe from user's report responses.
   */
  unsubscribeFromMyReports(userId: number): void {
    this.unsubscribe(`user/${userId}/reports`);
  }

  /**
   * Reset user unread count (when user opens reports modal).
   */
  resetUserUnreadCount(): void {
    this.userUnreadReportsCount.set(0);
  }

  /**
   * Reset admin unread count.
   */
  resetAdminUnreadCount(): void {
    this.unreadReportsCount.set(0);
  }

  /**
   * Set initial unread count from API.
   */
  setInitialUnreadCount(count: number, isAdmin: boolean): void {
    if (isAdmin) {
      this.unreadReportsCount.set(count);
    } else {
      this.userUnreadReportsCount.set(count);
    }
  }

  /**
   * Subscribe to dashboard stats updates.
   */
  subscribeToDashboardStats(callback: (stats: Record<string, unknown>) => void): void {
    this.subscribe('dashboard/stats', (data) => {
      if (data.type === 'stats_update' && data['stats']) {
        callback(data['stats'] as Record<string, unknown>);
      }
    });
  }

  /**
   * Check if connected to a specific topic.
   */
  isSubscribedTo(topic: string): boolean {
    const es = this.eventSources.get(topic);
    return es !== undefined && es.readyState === EventSource.OPEN;
  }
}
