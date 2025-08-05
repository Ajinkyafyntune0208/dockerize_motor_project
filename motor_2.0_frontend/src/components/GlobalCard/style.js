import styled from "styled-components";

// .container2inner {
//     padding: 0 15px 50px;
//     width: auto;
// }

// ::after,
//  ::before {
//     box-sizing: border-box;
// }

// .block1 {
//     margin-top: 1.5rem !important;
//     padding: 0 !important;
//     box-flex: 0;
//     flex: 0 0 75%;
//     max-width: 120%;
// }

const CardComponent = styled.div`
  border: none;
  border-radius: 10px 35px 0 35px;
  background-color: ${({ theme }) => theme.dark ? '#2a2a2a' : '#ffffff'} !important;
  transition: all 0.3s ease 0s;
  box-shadow: 0 10px 15px 6px rgba(0, 0, 0, 0.1),
    0 4px 6px -2px rgba(0, 0, 0, 0.05);
  width: auto;
  margin: 30px;
  @media (max-width: 767px) {
    margin: 15px 10px;
  }
`;


const CardBody = styled(CardComponent)`
  padding: 25.6px;
  padding: 1.6rem;
`;

const CardRow = styled(CardBody)`
  display: -webkit-box;
  display: -ms-flexbox;
  display: flex;
  -ms-flex-wrap: wrap;
  flex-wrap: wrap;
`;


const Row1 = styled(CardBody)`
  display: -webkit-box;
  display: -ms-flexbox;
  display: flex;
  -ms-flex-wrap: wrap;
  flex-wrap: wrap;
`;

const IconlessCardRow = styled(CardBody)`
  display: -webkit-box;
  display: -ms-flexbox;
  display: flex;
  -ms-flex-wrap: wrap;
  flex-wrap: wrap;
  border-radius:35px 35px 35px 35px;
`;

const Container2 = styled.div`
  box-flex: 0;
  flex: 0 0 100%;
  max-width: 100%;
  position: relative;
  width: 100%;
  min-height: 1px;
  padding-right: 15px;
  padding-left: 15px;
`;


const HeaderContainer = styled.div`
  box-flex: 0;
  flex: 0 0 100%;
  max-width: 100%;
  position: relative;
  width: 100%;
  min-height: 1px;
  padding-right: 15px;
  padding-left: 15px;
`;

const Header = styled.div`
  margin-top: -16px;
  font-size: 18px;
  font-weight: 600;
  letter-spacing: 1px;
  margin-left: 24px;
  .icon {
    font-size: 32px;
    color: #fff;
    background: #000;
    height: 40px;
    width: 40px;
    text-align: right;
    position: absolute;
    left: -24px;
    top: -24px;
    border-radius: 3px 5px 20px 5px;
    background-color: ${({ theme }) => (theme.Card?.color || '#f2c9fb')};
  }
  @media only screen and (max-width: 767px) and (min-width: 480px) {
    margin-top: -5px;
  }
  @media (max-width: 767px) {
    margin-top: -5px;
  }
`;

const HeaderBlue = styled.div`
  margin-top: -16px;
  font-size: 18px;
  font-weight: 600;
  letter-spacing: 1px;
  margin-left: 24px;
  .iconBlue {
    font-size: 32px;
    color: #fff;
    background: #000;
    height: 45px;
    width: 45px;
    text-align: right;
    position: absolute;
    left: -28px;
    top: -29px;
    border-radius: 100px;
    background-color: ${({ theme }) => (theme.CardBlue?.color || '#6334e3')};
  }
  @media only screen and (max-width: 767px) and (min-width: 480px) {
    margin-top: -5px;
  }
  @media (max-width: 767px) {
    margin-top: -5px;
  }
`;

const BottomHeader = styled.div`
  border-bottom: 1px dotted ${({ theme }) => (theme.CardLine?.color || '#d0ff37')};
  margin-right: 0%;
  margin-left: 0%;
  width: inherit;
  margin-top: 8px;
`;

const Container3 = styled.div`
  box-flex: 0;
  flex: 0 0 100%;
  max-width: 100%;
  position: relative;
  width: 100%;
  min-height: auto;
  padding-right: 18px;
  padding-left: 18px;
  margin-top: 3rem !important;
`;

const CardContentBox = styled.div`
  box-flex: 0;
  flex: 0 0 100%;
  max-width: 100%;
  position: relative;
  width: 100%;
  min-height: auto;
  padding-right: 18px;
  padding-left: 18px;
  margin-top: ${props => props.marginTop || "3rem !important"};
`;


export { HeaderContainer, CardContentBox, CardRow, Row1, IconlessCardRow, Container2, Header, BottomHeader, Container3, HeaderBlue };
