import React, { useState, useRef, useEffect } from 'react';
import {
    View,
    Text,
    TextInput,
    TouchableOpacity,
    FlatList,
    ActivityIndicator,
    Modal,
    ScrollView,
    StyleSheet,
    SafeAreaView,
    KeyboardAvoidingView,
    Platform,
    Alert,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';

/**
 * QUANLICS AI Chatbot Screen
 * Tư vấn Nghị định 81/2021/NĐ-CP
 */

// ==================== TYPES & INTERFACES ====================

interface Message {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    timestamp: string;
    citations?: string[];
}

interface ChatSession {
    phien_id: number;
    thoigian_batdau: string;
    thoigian_ketthuc?: string;
    diem_danhgia?: number;
}

const COLORS = {
    primary: '#1E3A8A',     // Dark Blue
    secondary: '#3B82F6',   // Blue
    accent: '#10B981',      // Emerald green cho trích dẫn
    background: '#F3F4F6',  // Light gray
    surface: '#FFFFFF',
    text: '#1F2937',
    textMuted: '#6B7280',
    border: '#E5E7EB',
    error: '#EF4444',
};

// CẤU HÌNH BASE URL: 10.0.2.2 cho máy ảo Android, thay bằng IP nội bộ (ví dụ: 192.168.1.x) nếu chạy máy thật
const BASE_URL = 'http://10.0.2.2:8000/api';

export default function ChatbotScreen() {
    // ==================== STATES ====================
    const [messages, setMessages] = useState<Message[]>([]);
    const [input, setInput] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [currentSessionId, setCurrentSessionId] = useState<number | null>(null);

    // History & Session States
    const [sessions, setSessions] = useState<ChatSession[]>([]);
    const [isHistoryModalVisible, setIsHistoryModalVisible] = useState(false);
    const [isLoadingHistory, setIsLoadingHistory] = useState(false);

    // Rating States
    const [isRatingModalVisible, setIsRatingModalVisible] = useState(false);
    const [ratingMessageId, setRatingMessageId] = useState<number | null>(null);
    const [selectedRating, setSelectedRating] = useState<number>(5);
    const [ratingNote, setRatingNote] = useState('');

    const flatListRef = useRef<FlatList>(null);

    // ==================== EFFECTS ====================
    useEffect(() => {
        startNewChat();
    }, []);

    // Tự động cuộn xuống khi có tin nhắn mới hoặc khi hệ thống đang phản hồi
    useEffect(() => {
        if (messages.length > 0) {
            setTimeout(() => {
                flatListRef.current?.scrollToEnd({ animated: true });
            }, 100);
        }
    }, [messages, isLoading]);

    // ==================== API HANDLERS ====================

    // Hàm lấy Token hệ thống từ bộ nhớ thiết bị
    const getAuthToken = async () => {
        try {
            const token = await AsyncStorage.getItem('auth_token');
            // LƯU Ý BẢO MẬT: Không được hardcode API KEY của Gemini tại đây.
            // Hệ thống cần trả về chuỗi Bearer token hợp lệ của tài khoản người dùng sau khi Đăng nhập thành công.
            return token || '';
        } catch (error) {
            console.error('Lỗi lấy token xác thực:', error);
            return '';
        }
    };

    // Tạo phiên chat mới hoàn toàn trên giao diện
    const startNewChat = () => {
        setMessages([
            {
                id: Date.now(),
                role: 'assistant',
                content: 'Xin chào! Tôi là trợ lý ảo hỗ trợ về Chính sách Miễn giảm học phí và Trợ cấp xã hội (Nghị định 81/2021). Bạn cần tôi giúp gì hôm nay?',
                timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
            }
        ]);
        setCurrentSessionId(null);
    };

    // Gửi tin nhắn lên Backend Laravel
    const handleSend = async () => {
        if (!input.trim() || isLoading) return;

        const userText = input.trim();
        setInput('');
        setIsLoading(true);

        const userMessage: Message = {
            id: Date.now(),
            role: 'user',
            content: userText,
            timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        };

        setMessages((prev: Message[]) => [...prev, userMessage]);

        try {
            const token = await getAuthToken();

            const response = await axios.post(
                `${BASE_URL}/chatbot/ask`,
                {
                    question: userText,
                    phien_id: currentSessionId
                },
                {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    timeout: 30000 // Timeout 30 giây chờ xử lý dữ liệu RAG
                }
            );

            if (response.data && response.data.success) {
                const dataAI = response.data.data;

                // Cập nhật lại phiên ID nếu đây là câu hỏi khởi tạo của phiên mới
                if (!currentSessionId && dataAI.phien_id) {
                    setCurrentSessionId(dataAI.phien_id);
                }

                const aiMessage: Message = {
                    id: dataAI.tin_nhan_tra_loi_id || (Date.now() + 1),
                    role: 'assistant',
                    content: dataAI.answer,
                    timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                    citations: dataAI.citations || [],
                };

                setMessages((prev: Message[]) => [...prev, aiMessage]);
            } else {
                throw new Error(response.data?.message || 'Không thể lấy dữ liệu từ trợ lý AI.');
            }
        } catch (error: any) {
            console.error('Chatbot API Error:', error);

            let errorMessage = 'Mất kết nối tới máy chủ. Vui lòng kiểm tra lại mạng hoặc IP backend!';
            if (error.response && error.response.data && error.response.data.message) {
                errorMessage = error.response.data.message;
            }

            const errorBotMessage: Message = {
                id: Date.now() + 2,
                role: 'assistant',
                content: `⚠️ Lỗi hệ thống: ${errorMessage}`,
                timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
            };
            setMessages((prev: Message[]) => [...prev, errorBotMessage]);
        } finally {
            setIsLoading(false);
        }
    };

    // Tải danh sách lịch sử các phiên chat
    const fetchChatSessions = async () => {
        setIsLoadingHistory(true);
        setIsHistoryModalVisible(true);
        try {
            const token = await getAuthToken();
            const response = await axios.get(`${BASE_URL}/chatbot/phien-list`, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json'
                }
            });

            if (response.data && response.data.success) {
                setSessions(response.data.data || []);
            }
        } catch (error) {
            console.error('Fetch sessions error:', error);
            Alert.alert('Lỗi', 'Không thể tải danh sách lịch sử cuộc trò chuyện.');
        } finally {
            setIsLoadingHistory(false);
        }
    };

    // Tải dữ liệu hội thoại chi tiết của phiên cũ
    const loadSessionDetail = async (phienId: number) => {
        setIsHistoryModalVisible(false);
        setIsLoading(true);
        try {
            const token = await getAuthToken();
            const response = await axios.get(`${BASE_URL}/chatbot/phien/${phienId}`, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json'
                }
            });

            if (response.data && response.data.success) {
                setCurrentSessionId(phienId);
                const historyMessages = response.data.data.map((msg: any) => ({
                    id: msg.id,
                    role: msg.role === 'user' ? 'user' : 'assistant',
                    content: msg.content,
                    timestamp: msg.timestamp || new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                    citations: msg.citations || [],
                }));
                setMessages(historyMessages);
            }
        } catch (error) {
            console.error('Load session detail error:', error);
            Alert.alert('Lỗi', 'Không thể mở dữ liệu chi tiết của phiên này.');
        } finally {
            setIsLoading(false);
        }
    };

    // Mở Modal đánh giá chất lượng câu trả lời
    const openRatingModal = (messageId: number) => {
        setRatingMessageId(messageId);
        setSelectedRating(5);
        setRatingNote('');
        setIsRatingModalVisible(true);
    };

    // Gửi đánh giá lên máy chủ
    const submitRating = async () => {
        if (!currentSessionId) return;
        try {
            const token = await getAuthToken();
            await axios.post(
                `${BASE_URL}/chatbot/phien/${currentSessionId}/danh-gia`,
                {
                    diem: selectedRating,
                    giai_thich: ratingNote
                },
                {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                }
            );

            Alert.alert('Thành công', 'Cảm ơn bạn đã đóng góp ý kiến để hoàn thiện hệ thống!');
        } catch (error) {
            console.error('Submit rating error:', error);
            Alert.alert('Thất bại', 'Không thể gửi đánh giá lúc này. Vui lòng thử lại sau.');
        } finally {
            setIsRatingModalVisible(false);
        }
    };

    // ==================== RENDERS ====================

    const renderMessageItem = ({ item }: { item: Message }) => {
        const isUser = item.role === 'user';
        return (
            <View style={[styles.messageRow, isUser ? styles.userRow : styles.botRow]}>
                {!isUser && (
                    <View style={styles.botAvatar}>
                        <Ionicons name="sparkles" size={16} color="#FFFFFF" />
                    </View>
                )}
                <View style={[styles.bubble, isUser ? styles.userBubble : styles.botBubble]}>
                    <Text style={[styles.messageText, isUser ? styles.userText : styles.botText]}>
                        {item.content}
                    </Text>

                    {/* Render nguồn trích dẫn pháp lý nếu có */}
                    {item.citations && item.citations.length > 0 && (
                        <View style={styles.citationsBlock}>
                            <Text style={styles.citationTitle}>📌 Tài liệu tham chiếu cơ sở:</Text>
                            <View style={styles.citationTagsContainer}>
                                {item.citations.map((cite: string, index: number) => (
                                    <View key={index} style={styles.citationTag}>
                                        <Text style={styles.citationTagText}>{cite}</Text>
                                    </View>
                                ))}
                            </View>
                        </View>
                    )}

                    <View style={styles.bubbleFooter}>
                        <Text style={styles.timestampText}>{item.timestamp}</Text>

                        {/* Nút đánh giá phản hồi dành riêng cho Bot */}
                        {!isUser && currentSessionId && (
                            <TouchableOpacity
                                style={styles.rateButton}
                                onPress={() => openRatingModal(item.id)}
                            >
                                <Ionicons name="star-outline" size={14} color={COLORS.secondary} />
                                <Text style={styles.rateButtonText}>Đánh giá</Text>
                            </TouchableOpacity>
                        )}
                    </View>
                </View>
            </View>
        );
    };

    return (
        <SafeAreaView style={styles.container}>
            {/* Header Màn Hình */}
            <View style={styles.header}>
                <TouchableOpacity style={styles.headerBtn} onPress={fetchChatSessions}>
                    <Ionicons name="time-outline" size={24} color={COLORS.primary} />
                </TouchableOpacity>
                <View style={styles.headerCenter}>
                    <Text style={styles.headerTitle}>QUANLICS AI Chatbot</Text>
                    <Text style={styles.headerSubtitle}>Tư vấn Nghị định 81/2021/NĐ-CP</Text>
                </View>
                <TouchableOpacity style={styles.headerBtn} onPress={startNewChat}>
                    <Ionicons name="add-circle-outline" size={24} color={COLORS.primary} />
                </TouchableOpacity>
            </View>

            {/* Danh Sách Tin Nhắn & Khung Chat */}
            <KeyboardAvoidingView
                behavior={Platform.OS === 'ios' ? 'padding' : undefined}
                style={styles.chatArea}
            >
                <FlatList
                    ref={flatListRef}
                    data={messages}
                    renderItem={renderMessageItem}
                    keyExtractor={(item: Message) => item.id.toString()}
                    contentContainerStyle={styles.messagesList}
                />

                {isLoading && (
                    <View style={styles.loadingContainer}>
                        <ActivityIndicator size="small" color={COLORS.primary} />
                        <Text style={styles.loadingText}>Trợ lý AI đang tra cứu cơ sở điều luật...</Text>
                    </View>
                )}

                {/* Thanh Nhập Nội Dung */}
                <View style={styles.inputContainer}>
                    <TextInput
                        style={styles.inputField}
                        placeholder="Nhập câu hỏi về chính sách học phí..."
                        placeholderTextColor={COLORS.textMuted}
                        value={input}
                        onChangeText={setInput}
                        multiline
                    />
                    <TouchableOpacity
                        style={[styles.sendButton, !input.trim() && styles.sendButtonDisabled]}
                        onPress={handleSend}
                        disabled={!input.trim() || isLoading}
                    >
                        <Ionicons name="send" size={18} color="#FFFFFF" />
                    </TouchableOpacity>
                </View>
            </KeyboardAvoidingView>

            {/* ==================== MODAL: LỊCH SỬ PHIÊN CHAT ==================== */}
            <Modal
                visible={isHistoryModalVisible}
                animationType="slide"
                transparent={true}
                onRequestClose={() => setIsHistoryModalVisible(false)}
            >
                <View style={styles.modalOverlay}>
                    <View style={styles.modalContent}>
                        <View style={styles.modalHeader}>
                            <Text style={styles.modalTitle}>Lịch sử tư vấn hệ thống</Text>
                            <TouchableOpacity onPress={() => setIsHistoryModalVisible(false)}>
                                <Ionicons name="close" size={24} color={COLORS.text} />
                            </TouchableOpacity>
                        </View>

                        {isLoadingHistory ? (
                            <ActivityIndicator style={{ margin: 30 }} size="large" color={COLORS.primary} />
                        ) : (
                            <ScrollView style={styles.sessionsList}>
                                {sessions.length === 0 ? (
                                    <Text style={styles.emptyText}>Chưa ghi nhận phiên tư vấn nào trước đây.</Text>
                                ) : (
                                    sessions.map((session: ChatSession) => (
                                        <TouchableOpacity
                                            key={session.phien_id}
                                            style={styles.sessionItem}
                                            onPress={() => loadSessionDetail(session.phien_id)}
                                        >
                                            <View style={styles.sessionInfo}>
                                                <Ionicons name="chatbox-ellipses-outline" size={20} color={COLORS.primary} />
                                                <Text style={styles.sessionDate}>Phiên #{session.phien_id} - {session.thoigian_batdau}</Text>
                                            </View>
                                            {session.diem_danhgia && (
                                                <View style={styles.ratingBadge}>
                                                    <Ionicons name="star" size={12} color="#FBBF24" />
                                                    <Text style={styles.ratingBadgeText}>{session.diem_danhgia}</Text>
                                                </View>
                                            )}
                                        </TouchableOpacity>
                                    ))
                                )}
                            </ScrollView>
                        )}
                    </View>
                </View>
            </Modal>

            {/* ==================== MODAL: ĐÁNH GIÁ CHẤT LƯỢNG ==================== */}
            <Modal
                visible={isRatingModalVisible}
                transparent={true}
                animationType="fade"
            >
                <View style={styles.ratingModalOverlay}>
                    <View style={styles.ratingModalContent}>
                        <Text style={styles.ratingTitle}>Đánh giá câu trả lời của AI</Text>

                        <View style={styles.starsContainer}>
                            {[1, 2, 3, 4, 5].map((star) => (
                                <TouchableOpacity key={star} onPress={() => setSelectedRating(star)}>
                                    <Text style={styles.star}>
                                        {star <= selectedRating ? '★' : '☆'}
                                    </Text>
                                </TouchableOpacity>
                            ))}
                        </View>

                        <TextInput
                            style={styles.ratingNoteInput}
                            placeholder="Ghi chú ý kiến đóng góp (không bắt buộc)..."
                            value={ratingNote}
                            onChangeText={setRatingNote}
                            multiline
                        />

                        <View style={styles.ratingButtonsRow}>
                            <TouchableOpacity
                                style={styles.ratingCancelButton}
                                onPress={() => setIsRatingModalVisible(false)}
                            >
                                <Text style={styles.ratingCancelButtonText}>Hủy</Text>
                            </TouchableOpacity>
                            <TouchableOpacity
                                style={styles.ratingSubmitButton}
                                onPress={submitRating}
                            >
                                <Text style={styles.ratingSubmitButtonText}>Gửi đánh giá</Text>
                            </TouchableOpacity>
                        </View>
                    </View>
                </View>
            </Modal>
        </SafeAreaView>
    );
}

// ==================== STYLE SHEETS ====================
const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: COLORS.background,
    },
    header: {
        height: 60,
        backgroundColor: COLORS.surface,
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        paddingHorizontal: 16,
        borderBottomWidth: 1,
        borderColor: COLORS.border,
        elevation: 2,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.1,
        shadowRadius: 2,
    },
    headerBtn: {
        padding: 4,
    },
    headerCenter: {
        alignItems: 'center',
    },
    headerTitle: {
        fontSize: 16,
        fontWeight: '700',
        color: COLORS.primary,
    },
    headerSubtitle: {
        fontSize: 11,
        color: COLORS.textMuted,
        marginTop: 2,
    },
    chatArea: {
        flex: 1,
    },
    messagesList: {
        padding: 16,
        paddingBottom: 24,
    },
    messageRow: {
        flexDirection: 'row',
        marginBottom: 16,
        maxWidth: '85%',
    },
    userRow: {
        alignSelf: 'flex-end',
        justifyContent: 'flex-end',
    },
    botRow: {
        alignSelf: 'flex-start',
        justifyContent: 'flex-start',
    },
    botAvatar: {
        width: 30,
        height: 30,
        borderRadius: 15,
        backgroundColor: COLORS.primary,
        alignItems: 'center',
        justifyContent: 'center',
        marginRight: 8,
        marginTop: 4,
    },
    bubble: {
        borderRadius: 16,
        paddingHorizontal: 14,
        paddingVertical: 10,
    },
    userBubble: {
        backgroundColor: COLORS.primary,
        borderBottomRightRadius: 2,
    },
    botBubble: {
        backgroundColor: COLORS.surface,
        borderBottomLeftRadius: 2,
        borderWidth: 1,
        borderColor: COLORS.border,
    },
    messageText: {
        fontSize: 14,
        lineHeight: 20,
    },
    userText: {
        color: '#FFFFFF',
    },
    botText: {
        color: COLORS.text,
    },
    bubbleFooter: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginTop: 6,
        gap: 15,
    },
    timestampText: {
        fontSize: 10,
        color: COLORS.textMuted,
    },
    rateButton: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 4,
        padding: 2,
    },
    rateButtonText: {
        fontSize: 11,
        color: COLORS.secondary,
    },
    citationsBlock: {
        marginTop: 8,
        paddingTop: 8,
        borderTopWidth: 1,
        borderTopColor: COLORS.border,
    },
    citationTitle: {
        fontSize: 11,
        fontWeight: '600',
        color: COLORS.textMuted,
        marginBottom: 4,
    },
    citationTagsContainer: {
        flexDirection: 'row',
        flexWrap: 'wrap',
        gap: 6,
    },
    citationTag: {
        backgroundColor: '#ECFDF5',
        borderColor: '#A7F3D0',
        borderWidth: 1,
        borderRadius: 4,
        paddingHorizontal: 6,
        paddingVertical: 2,
    },
    citationTagText: {
        fontSize: 11,
        color: COLORS.accent,
        fontWeight: '700',
    },
    loadingContainer: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
        gap: 8,
        paddingVertical: 8,
        backgroundColor: 'rgba(255,255,255,0.6)',
    },
    loadingText: {
        fontSize: 12,
        color: COLORS.textMuted,
    },
    inputContainer: {
        flexDirection: 'row',
        padding: 12,
        backgroundColor: COLORS.surface,
        borderTopWidth: 1,
        borderColor: COLORS.border,
        alignItems: 'center',
        gap: 8,
    },
    inputField: {
        flex: 1,
        backgroundColor: COLORS.background,
        borderRadius: 20,
        paddingHorizontal: 16,
        paddingVertical: 8,
        maxHeight: 100,
        fontSize: 14,
        color: COLORS.text,
    },
    sendButton: {
        width: 36,
        height: 36,
        borderRadius: 18,
        backgroundColor: COLORS.secondary,
        alignItems: 'center',
        justifyContent: 'center',
    },
    sendButtonDisabled: {
        backgroundColor: COLORS.border,
    },
    modalOverlay: {
        flex: 1,
        backgroundColor: 'rgba(0,0,0,0.5)',
        justifyContent: 'flex-end',
    },
    modalContent: {
        backgroundColor: COLORS.surface,
        borderTopLeftRadius: 20,
        borderTopRightRadius: 20,
        maxHeight: '80%',
        paddingBottom: 20,
    },
    modalHeader: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        padding: 16,
        borderBottomWidth: 1,
        borderColor: COLORS.border,
    },
    modalTitle: {
        fontSize: 16,
        fontWeight: '700',
        color: COLORS.text,
    },
    sessionsList: {
        padding: 16,
    },
    sessionItem: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        paddingVertical: 14,
        borderBottomWidth: 1,
        borderColor: COLORS.border,
    },
    sessionInfo: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 10,
    },
    sessionDate: {
        fontSize: 13,
        color: COLORS.text,
    },
    ratingBadge: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: '#FEF3C7',
        paddingHorizontal: 6,
        paddingVertical: 2,
        borderRadius: 4,
        gap: 4,
    },
    ratingBadgeText: {
        fontSize: 11,
        color: '#D97706',
        fontWeight: '700',
    },
    emptyText: {
        textAlign: 'center',
        color: COLORS.textMuted,
        marginTop: 20,
        fontSize: 13,
    },
    ratingModalOverlay: {
        flex: 1,
        backgroundColor: 'rgba(0,0,0,0.4)',
        justifyContent: 'center',
        alignItems: 'center',
        padding: 20,
    },
    ratingModalContent: {
        backgroundColor: '#FFFFFF',
        borderRadius: 16,
        padding: 20,
        width: '100%',
        maxWidth: 320,
    },
    ratingTitle: {
        fontSize: 16,
        fontWeight: '700',
        color: '#000000',
        marginBottom: 16,
        textAlign: 'center',
    },
    starsContainer: {
        flexDirection: 'row',
        justifyContent: 'center',
        gap: 12,
        marginBottom: 16,
    },
    star: {
        fontSize: 36,
        color: '#FBBF24',
    },
    ratingNoteInput: {
        backgroundColor: COLORS.background,
        borderRadius: 8,
        padding: 12,
        fontSize: 13,
        marginBottom: 16,
        minHeight: 80,
        textAlignVertical: 'top',
        borderWidth: 1,
        borderColor: COLORS.border,
        color: COLORS.text,
    },
    ratingButtonsRow: {
        flexDirection: 'row',
        gap: 12,
    },
    ratingCancelButton: {
        flex: 1,
        paddingVertical: 12,
        backgroundColor: COLORS.background,
        borderRadius: 8,
        alignItems: 'center',
    },
    ratingCancelButtonText: {
        fontSize: 14,
        color: COLORS.textMuted,
        fontWeight: '600',
    },
    ratingSubmitButton: {
        flex: 1,
        paddingVertical: 12,
        backgroundColor: COLORS.primary,
        borderRadius: 8,
        alignItems: 'center',
    },
    ratingSubmitButtonText: {
        fontSize: 14,
        color: '#FFFFFF',
        fontWeight: '600',
    },
});