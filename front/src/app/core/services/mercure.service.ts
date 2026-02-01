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
  private readonly maxReconnectAttempts = 15;
  private readonly baseReconnectDelay = 500; // Start faster
  private readonly maxReconnectDelay = 30000; // Max 30 seconds
  private heartbeatInterval: ReturnType<typeof setInterval> | null = null;

  ngOnDestroy(): void {
    this.closeAll();
    this.stopHeartbeat();
  }

  /**
   * Start heartbeat to monitor connection health
   */
  private startHeartbeat(): void {
    if (this.heartbeatInterval) return;

    this.heartbeatInterval = setInterval(() => {
      // Check if any connection is dead
      this.eventSources.forEach((es, topic) => {
        if (es.readyState === EventSource.CLOSED) {
          console.warn(`Heartbeat detected dead connection for topic: ${topic}`);
          const callback = this.callbacks.get(topic);
          if (callback) {
            this.eventSources.delete(topic);
            this.createEventSource(topic, 0);
          }
        }
      });
    }, 10000); // Check every 10 seconds
  }

  /**
   * Stop heartbeat monitoring
   */
  private stopHeartbeat(): void {
    if (this.heartbeatInterval) {
      clearInterval(this.heartbeatInterval);
      this.heartbeatInterval = null;
    }
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
        // Start heartbeat monitoring
        this.startHeartbeat();
      };

      eventSource.onmessage = (event) => {
        try {
          const data = JSON.parse(event.data) as MercureMessage;
          const callback = this.callbacks.get(topic);
          if (callback) {
            // Execute callback immediately for real-time response
            queueMicrotask(() => callback(data));
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

        // Reconnect with exponential backoff (capped)
        if (attempts < this.maxReconnectAttempts) {
          const delay = Math.min(
            this.baseReconnectDelay * Math.pow(1.5, attempts),
            this.maxReconnectDelay
          );
          console.log(`Reconnecting to ${topic} in ${delay}ms (attempt ${attempts + 1}/${this.maxReconnectAttempts})`);

          const timer = setTimeout(() => {
            this.createEventSource(topic, attempts + 1);
          }, delay);

          this.reconnectTimers.set(topic, timer);
        } else {
          console.error(`Max reconnect attempts reached for topic: ${topic}. Will retry on next user action.`);
          // Reset attempts counter so next manual action can trigger reconnect
          setTimeout(() => {
            const callback = this.callbacks.get(topic);
            if (callback && !this.eventSources.has(topic)) {
              console.log(`Auto-retrying connection to ${topic} after cooldown...`);
              this.createEventSource(topic, 0);
            }
          }, 60000); // Retry after 1 minute cooldown
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

  /**
   * Initialize all subscriptions for a user after login.
   * Call this after successful authentication.
   */
  initializeForUser(userId: number, isAdmin: boolean): void {
    console.log(`Initializing Mercure subscriptions for user ${userId}, isAdmin: ${isAdmin}`);

    // Always subscribe to dashboard stats (for active users count)
    this.subscribeToActiveUsers();

    if (isAdmin) {
      // Admin subscriptions
      this.subscribeToAdminReports(() => {});
      this.subscribeToAdminNotifications(() => {});
    } else {
      // Regular user subscriptions
      this.subscribeToMyReports(userId, () => {});
    }
  }

  /**
   * Force reconnect all active subscriptions.
   * Use this when user suspects connection issues.
   */
  reconnectAll(): void {
    console.log('Force reconnecting all Mercure subscriptions...');

    const topics = Array.from(this.callbacks.keys());

    // Close all existing connections
    this.eventSources.forEach(es => es.close());
    this.eventSources.clear();

    // Clear reconnect timers
    this.reconnectTimers.forEach(timer => clearTimeout(timer));
    this.reconnectTimers.clear();

    // Reconnect all
    topics.forEach(topic => {
      const callback = this.callbacks.get(topic);
      if (callback) {
        this.createEventSource(topic, 0);
      }
    });
  }

  /**
   * Get connection status for debugging.
   */
  getConnectionStatus(): { topic: string; status: string }[] {
    return Array.from(this.eventSources.entries()).map(([topic, es]) => ({
      topic,
      status: es.readyState === EventSource.OPEN ? 'connected' :
              es.readyState === EventSource.CONNECTING ? 'connecting' : 'closed'
    }));
  }
}
