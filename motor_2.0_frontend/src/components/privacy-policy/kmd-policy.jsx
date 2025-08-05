import React from 'react';
import styled from 'styled-components';

const DisclaimerWrapper = styled.div`

//   max-width: 600px;
  margin: 0 auto;
  padding: 20px;
//   border: 1px solid #ddd;
//   border-radius: 5px;
//   background-color: #f9f9f9;
`;

const Title = styled.h1`
  font-size: 24px;
  margin-bottom: 16px;
`;

const Paragraph = styled.p`
  font-size: 16px;
  line-height: 1.6;
  margin-bottom: 12px;
`;

const BoldText = styled.span`
  font-weight: bold;
`;

const ItalicText = styled.span`
  font-style: italic;
`;

const ImportantText = styled.span`
  color: red;
  font-weight: bold;
`;

export const Disclaimer = () => {
  return (
    <DisclaimerWrapper>
      <Title>Personal Identifiable Information (PII) Collection Disclaimer and Consent</Title>
      <Paragraph>
        By providing your <BoldText>Personal Identifiable Information (PII)</BoldText> to <ItalicText>[Company Name]</ItalicText>, you acknowledge and agree to the terms outlined in this disclaimer. We are committed to ensuring the privacy and security of your PII. This disclaimer informs you about how we collect, use, and protect your information.
      </Paragraph>
      <Paragraph>
        <BoldText>Information Collected:</BoldText> We may collect the following PII:
        <ul>
          <li>Full Name</li>
          <li>Email Address</li>
          <li>Phone Number</li>
          <li>Mailing Address</li>
          <li>Date of Birth</li>
          <li>Any other information you choose to provide</li>
        </ul>
      </Paragraph>
      <Paragraph>
        <BoldText>Purpose of Data Collection:</BoldText> The PII we collect will be used for the following purposes:
        <ul>
          <li>To provide and improve our services</li>
          <li>To personalize your experience</li>
          <li>To process transactions</li>
          <li>To send periodic emails regarding your order or other products and services</li>
          <li>To comply with legal obligations</li>
        </ul>
      </Paragraph>
      <Paragraph>
        <BoldText>Data Sharing and Protection:</BoldText> We do not sell, trade, or otherwise transfer your PII to outside parties, except as necessary for the operation of our business, compliance with the law, or protection of our rights. <ImportantText>We implement a variety of security measures to protect your PII.</ImportantText>
      </Paragraph>
      <Paragraph>
        <BoldText>Consent:</BoldText> By providing your PII, you consent to the collection, use, and sharing of your information as described in this disclaimer. <ImportantText>If you do not agree with any part of this disclaimer, please do not provide your PII.</ImportantText>
      </Paragraph>
    </DisclaimerWrapper>
  );
};

export default Disclaimer;
