import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { PaginationInfo } from '../models';

export interface ReportThread {
  id: number;
  subject: string;
  status: 'OPEN' | 'IN_PROGRESS' | 'RESOLVED' | 'CLOSED';
  sender: {
    id: number;
    email: string;
    nomComplet: string | null;
  };
  assignedAdmin: {
    id: number;
    email: string;
    nomComplet: string | null;
  } | null;
  lastMessage: string;
  createdAt: string;
  isRead: boolean;
}

export interface ReportMessage {
  id: number;
  message: string;
  type: 'USER_MESSAGE' | 'ADMIN_RESPONSE' | 'SYSTEM';
  sender: {
    id: number;
    email: string;
    nomComplet: string | null;
  };
  createdAt: string;
  isRead: boolean;
}

export interface ReportThreadsResponse {
  threads: ReportThread[];
  pagination?: PaginationInfo;
  unreadCount: number;
}

export interface ReportThreadDetailResponse {
  thread: ReportThread;
  messages: ReportMessage[];
}

export interface CreateReportRequest {
  subject?: string;
  message: string;
}

export interface CreateReportResponse {
  message: string;
  report: ReportThread;
}

export interface ReplyResponse {
  message: string;
  reply: ReportMessage;
}

export interface UpdateStatusResponse {
  message: string;
  thread: ReportThread;
}

export interface UnreadCountResponse {
  unreadCount: number;
}

@Injectable({
  providedIn: 'root'
})
export class ReportChatService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;

  // === User endpoints ===

  /**
   * Get my reports (as a user).
   */
  getMyReports(page = 1, limit = 20): Observable<ReportThreadsResponse> {
    const params = new HttpParams()
      .set('page', page.toString())
      .set('limit', limit.toString());

    return this.http.get<ReportThreadsResponse>(`${this.apiUrl}/me/reports`, { params });
  }

  /**
   * Create a new report.
   */
  createReport(request: CreateReportRequest): Observable<CreateReportResponse> {
    return this.http.post<CreateReportResponse>(`${this.apiUrl}/me/reports`, request);
  }

  // === Shared endpoints ===

  /**
   * Get a specific thread with all messages.
   */
  getThread(threadId: number): Observable<ReportThreadDetailResponse> {
    return this.http.get<ReportThreadDetailResponse>(`${this.apiUrl}/reports/${threadId}`);
  }

  /**
   * Reply to a thread.
   */
  replyToThread(threadId: number, message: string): Observable<ReplyResponse> {
    return this.http.post<ReplyResponse>(`${this.apiUrl}/reports/${threadId}/reply`, { message });
  }

  // === Admin endpoints ===

  /**
   * Get all reports (admin only).
   */
  getAllReports(page = 1, limit = 20, status?: string): Observable<ReportThreadsResponse> {
    let params = new HttpParams()
      .set('page', page.toString())
      .set('limit', limit.toString());

    if (status) {
      params = params.set('status', status);
    }

    return this.http.get<ReportThreadsResponse>(`${this.apiUrl}/admin/reports`, { params });
  }

  /**
   * Update thread status (admin only).
   */
  updateThreadStatus(threadId: number, status: string): Observable<UpdateStatusResponse> {
    return this.http.patch<UpdateStatusResponse>(
      `${this.apiUrl}/admin/reports/${threadId}/status`,
      { status }
    );
  }

  /**
   * Get unread count (admin only).
   */
  getUnreadCount(): Observable<UnreadCountResponse> {
    return this.http.get<UnreadCountResponse>(`${this.apiUrl}/admin/reports/unread-count`);
  }
}
