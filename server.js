import express from "express"
import fetch from "node-fetch"
import { v4 as uuidv4 } from "uuid"

const app = express()
app.use(express.json())
app.use(express.static("public"))

/* Demo database */
let users = {
  "1": { balance: 0 }
}

/* Sizning Checkout yoki payment API kalitingiz */
const API_KEY = "ODZlZjQ2YjY4NDViZDdjMDZiODE"

/* 1️⃣ To‘lov yaratish */
app.post("/create-payment", async (req,res)=>{

  const { amount, user } = req.body

  const order_id = uuidv4()

  /* Bu yerda checkout API ga request yuboriladi */
  const response = await fetch("https://checkout.uz/api/payment/create",{
    method:"POST",
    headers:{
      "Content-Type":"application/json",
      "Authorization":"Bearer "+API_KEY
    },
    body:JSON.stringify({
      amount: amount,
      currency: "UZS",
      order_id: order_id,
      description: "Balans to'ldirish",
      callback_url: "https://sizning-saytingiz.com/callback"
    })
  })

  const data = await response.json()

  res.json({
    payment_url: data.payment_url
  })

})

/* 2️⃣ To‘lov tasdiqlansa */
app.post("/callback",(req,res)=>{

  const { status, amount, user } = req.body

  if(status === "success"){

    users[user].balance += Number(amount)

    console.log("Balans qo'shildi:",amount)

  }

  res.send("OK")

})

/* 3️⃣ Balans ko‘rish */
app.get("/balance/:id",(req,res)=>{

  const id = req.params.id

  res.json({
    balance: users[id].balance
  })

})

app.listen(3000,()=>{
  console.log("Server ishlayapti: http://localhost:3000")
})
