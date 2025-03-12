// Firebase Configuration and Initialization Module

import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js';
import { getAnalytics, logEvent } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-analytics.js';
import { 
    getFirestore, 
    doc, 
    setDoc, 
    collection, 
    addDoc,
    serverTimestamp,
    Timestamp,
    getDoc, 
    query, 
    where, 
    orderBy, 
    limit, 
    getDocs, 
    updateDoc,
    deleteDoc,
    onSnapshot
} from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-firestore.js';
import { 
    getAuth, 
    createUserWithEmailAndPassword, 
    signInWithEmailAndPassword,
    signOut,
    setPersistence,
    browserLocalPersistence,
    browserSessionPersistence,
    onAuthStateChanged,
    sendEmailVerification
} from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js';

// Comprehensive Firebase Configuration
const firebaseConfig = {
    apiKey: "AIzaSyDJPSBZyfLevRlMTyEQAVJKBe0qc_cyF8M",
    authDomain: "databasemcpipe.firebaseapp.com",
    databaseURL: "https://databasemcpipe-default-rtdb.asia-southeast1.firebasedatabase.app",
    projectId: "databasemcpipe",
    storageBucket: "databasemcpipe.firebasestorage.app",
    messagingSenderId: "1031692818139",
    appId: "1:1031692818139:web:7bc75ac648b69775379efe",
    measurementId: "G-V6NNEZD66V"
};

// Configuration Validation Function
function validateFirebaseConfig(config) {
    const requiredFields = [
        'apiKey', 
        'authDomain', 
        'projectId', 
        'storageBucket', 
        'messagingSenderId', 
        'appId'
    ];

    for (const field of requiredFields) {
        if (!config[field] || config[field].trim() === '') {
            console.error(`Invalid Firebase configuration: ${field} is missing or empty`);
            throw new Error(`Firebase Configuration Error: ${field} is required`);
        }
    }

    // Additional validation checks
    if (config.apiKey.length < 20) {
        throw new Error('Invalid API key: key seems too short');
    }
}

// Firebase Initialization with Enhanced Error Handling
function initializeFirebase(config) {
    try {
        // Validate configuration
        validateFirebaseConfig(config);

        // Initialize Firebase app
        const app = initializeApp(config);
        const auth = getAuth(app);
        const firestore = getFirestore(app);
        const analytics = getAnalytics(app);

        // Configure authentication persistence
        setPersistence(auth, browserLocalPersistence)
            .catch((error) => {
                console.error('Authentication persistence error:', error);
            });

        return { app, auth, firestore, analytics };

    } catch (error) {
        console.error('Firebase Initialization Error:', error);
        
        // Detailed error logging
        const errorDetails = `
        🚨 Firebase Initialization Failed 🚨
        
        Possible Causes:
        1. Incorrect API configuration
        2. Network connectivity issues
        3. Invalid project credentials

        Troubleshooting Steps:
        a) Verify Firebase project settings
        b) Check network connection
        c) Confirm API key and project ID
        `;
        
        console.warn(errorDetails);
        throw error;
    }
}

// User Registration Function with Enhanced Error Handling
async function registerUser(email, password, additionalData = {}) {
    try {
        const { auth, firestore } = initializeFirebase(firebaseConfig);
        
        // Create user in Firebase Authentication
        const userCredential = await createUserWithEmailAndPassword(auth, email, password);
        const user = userCredential.user;

        // Create user document in Firestore
        const userDocRef = doc(firestore, 'users', user.uid);
        await setDoc(userDocRef, {
            ...additionalData,
            email: user.email,
            createdAt: serverTimestamp(),
            lastLoginAt: serverTimestamp()
        }, { merge: true });

        return user;
    } catch (error) {
        console.error('User Registration Error:', error);
        
        // Specific error handling
        switch (error.code) {
            case 'auth/email-already-in-use':
                throw new Error('Email is already registered');
            case 'auth/invalid-email':
                throw new Error('Invalid email format');
            case 'auth/weak-password':
                throw new Error('Password is too weak');
            default:
                throw error;
        }
    }
}

// Initialize Firebase
const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
const db = getFirestore(app);
const analytics = getAnalytics(app);

// Export initialized instances and functions
export { 
    app, 
    auth, 
    db, 
    analytics,
    createUserWithEmailAndPassword,
    signInWithEmailAndPassword,
    signOut,
    setPersistence,
    browserLocalPersistence,
    browserSessionPersistence,
    onAuthStateChanged,
    sendEmailVerification,
    getDoc,
    query,
    where,
    orderBy,
    limit,
    getDocs,
    updateDoc,
    deleteDoc,
    doc,
    collection,
    addDoc,
    setDoc,
    Timestamp,
    serverTimestamp,
    onSnapshot,
    registerUser
};
