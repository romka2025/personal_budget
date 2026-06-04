erDiagram
    users {
        INT user_id PK
        VARCHAR name
        VARCHAR email
        VARCHAR password
        TIMESTAMP created_at
    }
    categories {
        INT category_id PK
        INT user_id FK
        VARCHAR name
        ENUM type
    }
    transactions {
        INT transaction_id PK
        INT user_id FK
        DECIMAL amount
        ENUM type
        INT category_id FK
        DATE date
        VARCHAR description
    }
    goals {
        INT goal_id PK
        INT user_id FK
        DECIMAL target_amount
        DECIMAL allocated_amount
        VARCHAR description
        DATE deadline
        ENUM status
    }
    budgets {
        INT budget_id PK
        INT user_id FK
        INT category_id FK
        DECIMAL monthly_limit
    }
    users ||--o{ transactions : "מבצע"
    users ||--o{ categories : "מגדיר"
    users ||--o{ goals : "קובע"
    users ||--o{ budgets : "מגדיר"
    categories ||--o{ transactions : "משויכת ל"
    categories ||--o{ budgets : "נכלל ב"